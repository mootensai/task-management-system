<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class TaskSearch extends Task
{
    public $status;
    public $priority;
    public $assigned_to;
    public $tags; // comma-separated IDs
    public $keyword; // title/description
    public $due_date_from;
    public $due_date_to;
    public $show_deleted = false; // Show soft-deleted tasks

    public function rules(): array
    {
        return [
            [['status', 'priority', 'keyword'], 'string'],
            [['assigned_to'], 'integer'],
            [['tags'], 'string'],
            [['due_date_from', 'due_date_to'], 'date', 'format' => 'php:Y-m-d'],
            [['show_deleted'], 'boolean'],
        ];
    }

    public function search(array $params): ActiveDataProvider
    {
        // Use findWithDeleted if show_deleted is true, otherwise use find() (excludes deleted)
        $query = $params['show_deleted'] ?? false
            ? Task::findWithDeleted()->with(['assignedUser', 'tags'])
            : Task::find()->with(['assignedUser', 'tags']);
        $table = static::tableName();
        
        // If showing deleted, filter to only show deleted tasks
        if ($this->show_deleted) {
            $query->andWhere(['IS NOT', "$table.deleted_at", null]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => (int)($params['pageSize'] ?? 20),
            ],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
                'attributes' => [
                    'created_at', 'due_date', 'priority', 'title', 'status',
                ],
            ],
        ]);

        $this->load($params, '');
        if (!$this->validate()) {
            return $dataProvider;
        }

        if ($this->status) {
            $query->andWhere([$table.'.status' => $this->status]);
        }
        if ($this->priority) {
            $query->andWhere([$table.'.priority' => $this->priority]);
        }
        if ($this->assigned_to) {
            $query->andWhere([$table.'.assigned_to' => $this->assigned_to]);
        }
        if ($this->keyword) {
            $query->andFilterWhere(['or',
                ['like', $table.'.title', $this->keyword],
                ['like', $table.'.description', $this->keyword],
            ]);
        }
        if ($this->due_date_from) {
            $query->andWhere(['>=', $table.'.due_date', $this->due_date_from]);
        }
        if ($this->due_date_to) {
            $query->andWhere(['<=', $table.'.due_date', $this->due_date_to]);
        }
        if ($this->tags) {
            $tagIds = array_filter(array_map('intval', explode(',', $this->tags)));
            if ($tagIds) {
                $query->joinWith('tags tt', false)->andWhere(['tt.id' => $tagIds])->groupBy($table.'.id');
            }
        }

        return $dataProvider;
    }
}
