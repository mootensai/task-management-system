<?php

namespace app\modules\api\controllers;

use app\models\Tag;
use app\models\Task;
use app\models\TaskSearch;
use app\models\User;
use app\modules\api\components\JwtOrBearerAuth;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\rest\Serializer;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class TaskController extends Controller
{
    public $serializer = [
        'class' => Serializer::class,
        'collectionEnvelope' => 'items',
    ];

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
        ];

        $behaviors['authenticator'] = [
            'class' => JwtOrBearerAuth::class,
            'except' => [],
        ];

        return $behaviors;
    }

    protected function serializeData($data)
    {
        if ($data instanceof ActiveDataProvider) {
            // Serialize models with relations
            $models = $data->getModels();
            $serializedModels = [];
            foreach ($models as $model) {
                if ($model instanceof Task) {
                    $serializedModels[] = $model->toArray([], ['assignedUser', 'tags']);
                } else {
                    $serializedModels[] = $model->toArray();
                }
            }
            
            $pagination = $data->getPagination();
            return [
                'items' => $serializedModels,
                '_meta' => [
                    'totalCount' => $data->getTotalCount(),
                    'pageCount' => $pagination ? $pagination->getPageCount() : 1,
                    'currentPage' => $pagination ? $pagination->getPage() + 1 : 1,
                    'perPage' => $pagination ? $pagination->getPageSize() : count($serializedModels),
                ],
            ];
        }
        
        return parent::serializeData($data);
    }

    protected function verbs()
    {
        return [
            'index' => ['GET'],
            'view' => ['GET'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
            'toggle-status' => ['PATCH'],
            'restore' => ['PATCH'],
        ];
    }

    public function actionIndex(): ActiveDataProvider
    {
        $search = new TaskSearch();
        return $search->search(Yii::$app->request->get());
    }

    public function actionView(int $id): array
    {
        $task = $this->findModel($id);
        $this->checkAccess('view', $task);
        return $task->toArray([], ['assignedUser', 'tags']);
    }

    public function actionCreate()
    {
        $task = new Task();
        $body = Yii::$app->request->bodyParams;
        $task->load($body, '');

        // Validate before saving
        if (!$task->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $task->getErrors()];
        }

        if (!$task->save()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $task->getErrors()];
        }

        // Assign tags if provided
        $this->syncTags($task, $body['tag_ids'] ?? []);

        Yii::$app->response->statusCode = 201;
        return $task->toArray([], ['assignedUser', 'tags']);
    }

    public function actionUpdate(int $id)
    {
        $task = $this->findModel($id);
        $this->checkAccess('update', $task);

        $body = Yii::$app->request->bodyParams;

        // Handle version for optimistic locking if provided
        if (isset($body['version'])) {
            $task->version = (int)$body['version'];
        }

        $task->load($body, '');

        try {
            if (!$task->save()) {
                Yii::$app->response->statusCode = 422;
                return ['errors' => $task->getErrors()];
            }
        } catch (StaleObjectException $e) {
            throw new ConflictHttpException('Version conflict while updating task.');
        }

        if (array_key_exists('tag_ids', $body)) {
            $this->syncTags($task, $body['tag_ids']);
        }

        return $task->toArray([], ['assignedUser', 'tags']);
    }

    public function actionDelete(int $id)
    {
        $task = $this->findModel($id);
        $this->checkAccess('delete', $task);

        // Handle version for optimistic locking if provided
        $body = Yii::$app->request->bodyParams;
        if (isset($body['version'])) {
            $task->version = (int)$body['version'];
        }

        try {
            // Soft delete via behavior
            $task->delete();
        } catch (StaleObjectException $e) {
            throw new ConflictHttpException('Version conflict while deleting task.');
        }

        Yii::$app->response->statusCode = 204;
        return null;
    }

    public function actionToggleStatus(int $id)
    {
        $task = $this->findModel($id);
        $this->checkAccess('update', $task);

        // Handle version for optimistic locking if provided in request body
        $body = Yii::$app->request->bodyParams;
        if (isset($body['version'])) {
            $task->version = (int)$body['version'];
        }

        try {
            if (!$task->toggleStatus()) {
                Yii::$app->response->statusCode = 422;
                return ['errors' => $task->getErrors()];
            }
        } catch (StaleObjectException $e) {
            throw new ConflictHttpException('Version conflict while toggling status.');
        }

        return $task->toArray();
    }

    public function actionRestore(int $id)
    {
        $task = Task::findWithDeleted()->with(['assignedUser', 'tags'])->andWhere(['id' => $id])->one();
        if (!$task) {
            throw new NotFoundHttpException('Task not found');
        }
        $this->checkAccess('update', $task);

        // Handle version for optimistic locking if provided in request body
        $body = Yii::$app->request->bodyParams;
        if (isset($body['version'])) {
            $task->version = (int)$body['version'];
        }

        try {
            // Use behavior restore method
            $softDeleteBehavior = $task->getBehavior('softDelete');
            if ($softDeleteBehavior && method_exists($softDeleteBehavior, 'restore')) {
                $restored = $softDeleteBehavior->restore();
            } else {
                // Fallback: clear deleted_at manually
                $task->deleted_at = null;
                $restored = $task->save(false, ['deleted_at']);
            }
        } catch (StaleObjectException $e) {
            throw new ConflictHttpException('Version conflict while restoring task.');
        }

        if (!$restored) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Unable to restore task'];
        }

        return $task->toArray([], ['assignedUser', 'tags']);
    }

    protected function findModel(int $id): Task
    {
        $model = Task::find()->with(['assignedUser', 'tags'])->andWhere(['id' => $id])->one();
        if (!$model) {
            throw new NotFoundHttpException('Task not found');
        }
        return $model;
    }

    protected function syncTags(Task $task, $tagIds): void
    {
        if (!is_array($tagIds)) {
            return;
        }
        $validIds = Tag::find()->select('id')->where(['id' => $tagIds])->column();
        $rows = [];
        $now = time();
        foreach ($validIds as $tid) {
            $rows[] = [$task->id, (int)$tid, $now];
        }
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            // Clear existing
            $db->createCommand()->delete('{{%task_tag}}', ['task_id' => $task->id])->execute();
            // Insert new
            if ($rows) {
                $db->createCommand()->batchInsert('{{%task_tag}}', ['task_id', 'tag_id', 'created_at'], $rows)->execute();
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
        }
    }

    protected function checkAccess($action, $model = null)
    {
        // Simple role-based check: admin => allow all; user => own assigned tasks
        /** @var User $user */
        $user = Yii::$app->user->identity;
        if (!$user) {
            throw new ForbiddenHttpException('Authentication required');
        }
        if ($user->role === 'admin') {
            return true;
        }
        if ($model instanceof Task) {
            if ((int)$model->assigned_to !== (int)$user->id) {
                throw new ForbiddenHttpException('You do not have permission to access this task');
            }
        }
        return true;
    }
}
