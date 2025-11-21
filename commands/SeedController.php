<?php

namespace app\commands;

use app\models\User;
use app\models\Task;
use app\models\Tag;
use yii\console\Controller;
use yii\console\ExitCode;
use Yii;

/**
 * Database seeder for populating test data
 */
class SeedController extends Controller
{
    /**
     * Seed all tables with test data
     * @return int Exit code
     */
    public function actionIndex()
    {
        $this->stdout("Seeding database...\n");
        
        $this->actionUsers();
        $this->actionTags();
        $this->actionTasks();
        
        $this->stdout("Database seeding completed!\n");
        return ExitCode::OK;
    }

    /**
     * Seed users table
     * @return int Exit code
     */
    public function actionUsers()
    {
        $this->stdout("Seeding users...\n");

        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'password' => 'Secret123',
            ],
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'user',
                'password' => 'user123',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'role' => 'user',
                'password' => 'user123',
            ],
        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->name = $userData['name'];
            $user->email = $userData['email'];
            $user->role = $userData['role'];
            $user->auth_key = Yii::$app->security->generateRandomString();
            $user->password_hash = Yii::$app->security->generatePasswordHash($userData['password']);
            $user->access_token = Yii::$app->security->generateRandomString(64);
            $user->status = 10;
            
            if ($user->save()) {
                $this->stdout("  - Created user: {$user->email} (access_token: {$user->access_token})\n");
            } else {
                $this->stderr("  - Failed to create user: {$user->email}\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Seed tags table
     * @return int Exit code
     */
    public function actionTags()
    {
        $this->stdout("Seeding tags...\n");

        $tags = [
            ['name' => 'Bug', 'color' => '#dc3545'],
            ['name' => 'Feature', 'color' => '#28a745'],
            ['name' => 'Enhancement', 'color' => '#007bff'],
            ['name' => 'Documentation', 'color' => '#17a2b8'],
            ['name' => 'Urgent', 'color' => '#ffc107'],
        ];

        foreach ($tags as $tagData) {
            $tag = new Tag();
            $tag->name = $tagData['name'];
            $tag->color = $tagData['color'];
            $tag->created_at = date('Y-m-d H:i:s');
            
            if ($tag->save()) {
                $this->stdout("  - Created tag: {$tag->name}\n");
            } else {
                $this->stderr("  - Failed to create tag: {$tag->name}\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Seed tasks table
     * @return int Exit code
     */
    public function actionTasks()
    {
        $this->stdout("Seeding tasks...\n");

        $users = User::find()->all();
        $tags = Tag::find()->all();

        if (empty($users)) {
            $this->stderr("No users found. Please seed users first.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $tasks = [
            [
                'title' => 'Fix login authentication bug',
                'description' => 'Users are unable to login with valid credentials. Need to investigate the authentication service.',
                'status' => 'in_progress',
                'priority' => 'high',
                'due_date' => date('Y-m-d', strtotime('+3 days')),
                'tag_names' => ['Bug', 'Urgent'],
            ],
            [
                'title' => 'Implement user profile page',
                'description' => 'Create a comprehensive user profile page with edit capabilities.',
                'status' => 'pending',
                'priority' => 'medium',
                'due_date' => date('Y-m-d', strtotime('+1 week')),
                'tag_names' => ['Feature'],
            ],
            [
                'title' => 'Update API documentation',
                'description' => 'The API documentation is outdated and needs to be updated with new endpoints.',
                'status' => 'pending',
                'priority' => 'low',
                'due_date' => date('Y-m-d', strtotime('+2 weeks')),
                'tag_names' => ['Documentation'],
            ],
            [
                'title' => 'Optimize database queries',
                'description' => 'Several queries are running slow and need optimization.',
                'status' => 'completed',
                'priority' => 'high',
                'due_date' => date('Y-m-d', strtotime('-3 days')),
                'tag_names' => ['Enhancement'],
            ],
            [
                'title' => 'Add email notifications',
                'description' => 'Implement email notifications for task assignments and status changes.',
                'status' => 'pending',
                'priority' => 'medium',
                'due_date' => date('Y-m-d', strtotime('+5 days')),
                'tag_names' => ['Feature', 'Enhancement'],
            ],
        ];

        foreach ($tasks as $index => $taskData) {
            $task = new Task();
            $task->title = $taskData['title'];
            $task->description = $taskData['description'];
            $task->status = $taskData['status'];
            $task->priority = $taskData['priority'];
            $task->due_date = $taskData['due_date'];
            $task->assigned_to = $users[$index % count($users)]->id;
            $task->metadata = json_encode(['source' => 'seeder', 'index' => $index]);
            
            if ($task->save()) {
                // Assign tags
                foreach ($taskData['tag_names'] as $tagName) {
                    $tag = array_filter($tags, function($t) use ($tagName) {
                        return $t->name === $tagName;
                    });
                    if (!empty($tag)) {
                        $tag = reset($tag);
                        // Manually insert into junction table with created_at timestamp
                        $createdAt = time();
                        Yii::$app->db->createCommand()
                            ->insert('{{%task_tag}}', [
                                'task_id' => $task->id,
                                'tag_id' => $tag->id,
                                'created_at' => $createdAt,
                            ])
                            ->execute();
                    }
                }
                
                $this->stdout("  - Created task: {$task->title}\n");
            } else {
                $this->stderr("  - Failed to create task: {$task->title}\n");
                print_r($task->errors);
            }
        }

        return ExitCode::OK;
    }
}
