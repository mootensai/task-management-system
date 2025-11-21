<?php

namespace app\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;

/**
 * SoftDeleteBehavior implements soft deletion by setting a deleted_at timestamp
 * instead of physically deleting the record. It also provides a restore() helper.
 */
class SoftDeleteBehavior extends Behavior
{
    /** @var string attribute name storing the UNIX timestamp of deletion */
    public string $deletedAtAttribute = 'deleted_at';

    public function events(): array
    {
        return [
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    /**
     * Intercepts delete() calls to perform soft delete, then cancels real deletion.
     */
    public function beforeDelete(Event $event): void
    {
        $owner = $this->owner;
        if (!$owner instanceof ActiveRecord) {
            return;
        }

        // If already deleted, allow original delete() to proceed (hard delete)
        if ($this->isDeleted()) {
            return;
        }

        $attr = $this->deletedAtAttribute;
        $owner->$attr = time();
        // Persist only the deleted_at attribute without validation
        $owner->update(false, [$attr]);

        // Cancel the actual delete operation
        $event->isValid = false;
    }

    /**
     * Returns whether the record is soft-deleted.
     */
    public function isDeleted(): bool
    {
        $attr = $this->deletedAtAttribute;
        return (bool)$this->owner->$attr;
    }

    /**
     * Explicitly soft delete the record, similar to calling delete().
     */
    public function softDelete(): bool
    {
        $owner = $this->owner;
        if ($this->isDeleted()) {
            return true;
        }
        $attr = $this->deletedAtAttribute;
        $owner->$attr = time();
        return $owner->update(false, [$attr]) !== false;
    }

    /**
     * Restore a soft-deleted record by clearing the deleted_at attribute.
     */
    public function restore(): bool
    {
        $owner = $this->owner;
        if (!$owner instanceof ActiveRecord) {
            return false;
        }
        
        // For optimistic locking, get the latest version directly from database using raw SQL
        if ($owner->hasAttribute('version') && !$owner->isNewRecord) {
            $pk = $owner->getPrimaryKey();
            $pkName = $owner::primaryKey()[0];
            $tableName = $owner::tableName();
            $db = Yii::$app->db;
            $quotedTableName = $db->schema->quoteTableName($tableName);
            $quotedPkName = $db->schema->quoteColumnName($pkName);
            $quotedVersionName = $db->schema->quoteColumnName('version');
            
            $version = $db->createCommand(
                "SELECT {$quotedVersionName} FROM {$quotedTableName} WHERE {$quotedPkName} = :id",
                [':id' => $pk]
            )->queryScalar();
            
            if ($version !== false) {
                $owner->version = (int)$version;
            }
        } else {
            // Refresh normally if no optimistic locking
            try {
                $owner->refresh();
            } catch (\Exception $e) {
                // If refresh fails (e.g., record not found in default scope), 
                // try to get fresh instance using findWithDeleted if available
                if (method_exists($owner, 'findWithDeleted')) {
                    $pk = $owner->getPrimaryKey();
                    $pkName = $owner::primaryKey()[0];
                    $fresh = $owner::findWithDeleted()->andWhere([$pkName => $pk])->one();
                    if ($fresh) {
                        $owner->setAttributes($fresh->getAttributes(), false);
                    }
                }
            }
        }
        
        $attr = $this->deletedAtAttribute;
        $owner->$attr = null;
        return $owner->update(false, [$attr]) !== false;
    }
}
