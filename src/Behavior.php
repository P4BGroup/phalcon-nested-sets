<?php

namespace P4BGroup\NestedSets;

use Phalcon\Mvc\Model\Behavior as PhalconBehavior;
use Phalcon\Mvc\Model\MetaDataInterface;
use Phalcon\Mvc\ModelInterface;

class Behavior extends PhalconBehavior
{
    /**
     * @var string
     */
    private static $primaryKey = 'id';
    /**
     * @var string
     */
    private static $leftKey = 'lft';
    /**
     * @var string
     */
    private static $rightKey = 'rgt';
    /**
     * @var string
     */
    private static $depthKey = 'depth';
    /**
     * @var string
     */
    private static $parentKey = 'parent_id';
    /**
     * @var string
     */
    private static $primaryDbColumn;
    /**
     * @var string
     */
    private static $parentDbColumn;
    /**
     * @var string
     */
    private static $leftDbColumn;
    /**
     * @var string
     */
    private static $rightDbColumn;
    /**
     * @var string
     */
    private static $depthDbColumn;

    /**
     * @param string $type
     * @param ModelInterface $model
     * @return void
     */
    public function notify($type, ModelInterface $model): void
    {
        $options = $this->getOptions();
        self::$primaryKey = $options['primaryKey'] ?? self::$primaryKey;
        self::$leftKey = $options['leftKey'] ?? self::$leftKey;
        self::$rightKey = $options['rightKey'] ?? self::$rightKey;
        self::$depthKey = $options['depthKey'] ?? self::$depthKey;
        self::$parentKey = $options['parentKey'] ?? self::$parentKey;

        /** @var MetaDataInterface $metaData */
        $metaData = $model->getModelsMetadata();
        $columnMap = $metaData->getReverseColumnMap($model);

        self::$primaryDbColumn = $columnMap[self::$primaryKey] ?? self::$primaryKey;
        self::$parentDbColumn = $columnMap[self::$parentKey] ?? self::$parentKey;
        self::$leftDbColumn = $columnMap[self::$leftKey] ?? self::$leftKey;
        self::$rightDbColumn = $columnMap[self::$rightKey] ?? self::$rightKey;
        self::$depthDbColumn = $columnMap[self::$depthKey] ?? self::$depthKey;

        switch ($type) {
            case 'beforeCreate':
                $this->processCreateNodeWithParent($model);
                break;
            case 'beforeUpdate':
                $this->processUpdate($model);
                break;
            case 'beforeDelete':
                $this->processDelete($model);
                break;
        }
    }

    /**
     * @param ModelInterface $model
     */
    public function processCreateRootNode(ModelInterface $model): void
    {
        $max = $model::maximum(['column' => self::$rightKey]);
        $data = [
            self::$leftKey => $max + 1,
            self::$rightKey => $max + 2,
            self::$depthKey => 0,
        ];
        $model->assign($data);
    }

    /**
     * @param ModelInterface $model
     */
    public function processCreateNodeWithParent(ModelInterface $model): void
    {
        $parent = $model::findFirst([
            self::$primaryKey . ' = :parent:',
            'bind' => [
                'parent' => $model->readAttribute(self::$parentKey)
            ]
        ]);

        if (!$parent) {
            $this->processCreateRootNode($model);
            return;
        }

        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$rightDbColumn . '` = `' . self::$rightDbColumn . '` + 2 ' .
            'WHERE `' . self::$rightDbColumn . '` >= :right';
        $model->getWriteConnection()->query($query, [
            'right' => $parent->readAttribute(self::$rightKey)
        ]);

        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$leftDbColumn . '` = `' . self::$leftDbColumn . '` + 2 ' .
            'WHERE `' . self::$leftDbColumn . '` >= :right';
        $model->getWriteConnection()->query($query, [
            'right' => $parent->readAttribute(self::$rightKey)
        ]);

        $model->assign([
            self::$leftKey => $parent->readAttribute(self::$rightKey),
            self::$rightKey => $parent->readAttribute(self::$rightKey) + 1,
            self::$depthKey => $parent->readAttribute(self::$depthKey) + 1,
        ]);
    }

    /**
     * @param ModelInterface $model
     */
    public function processDelete(ModelInterface $model): void
    {
        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$rightDbColumn . '` = `' . self::$rightDbColumn . '` - 1, ' .
            '`' . self::$leftDbColumn . '` = `' . self::$leftDbColumn . '` - 1, ' .
            '`' . self::$depthDbColumn . '` = `' . self::$depthDbColumn . '` - 1 ' .
            'WHERE `' . self::$leftDbColumn . '` > :left AND `' . self::$rightDbColumn . '` < :right';

        $model->getWriteConnection()->query($query, [
            'right' => $model->readAttribute(self::$rightKey),
            'left' => $model->readAttribute(self::$leftKey),
        ]);

        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$rightDbColumn . '` = `' . self::$rightDbColumn . '` - 2 ' .
            'WHERE `' . self::$rightDbColumn . '` > :right';
        $model->getWriteConnection()->query($query, [
            'right' => $model->readAttribute(self::$rightKey)
        ]);

        $query = 'UPDATE `' . $model->getSource() . '` SET' .
            ' `' . self::$leftDbColumn . '` = `' . self::$leftDbColumn . '` - 2 ' .
            'WHERE `' . self::$leftDbColumn . '` > :right';
        $model->getWriteConnection()->query($query, [
            'right' => $model->readAttribute(self::$rightKey)
        ]);

        // update parent for immediate sub-nodes of current category
        $query = 'UPDATE `' . $model->getSource() . '` SET' .
            ' `' . self::$parentDbColumn . '` = 0 ' .
            'WHERE `' . self::$parentDbColumn . '` = :parent';
        $model->getWriteConnection()->query($query, [
            'parent' => $model->readAttribute(self::$primaryKey)
        ]);
    }

    /**
     * @param ModelInterface $model
     */
    public function processUpdate(ModelInterface $model): void
    {
        $currentModel = $model::findFirst([
            self::$primaryKey . ' = :id:',
            'bind' => [
                'id' => $model->readAttribute(self::$primaryKey)
            ]
        ]);
        $parentModel = $model::findFirst([
            self::$primaryKey . ' = :id:',
            'bind' => [
                'id' => $model->readAttribute(self::$parentKey)
            ]
        ]);

        // parent has changed (unset parent or set a new parent)
        if ($model->readAttribute(self::$parentKey) == $currentModel->readAttribute(self::$parentKey)) {
            return;
        }

        // upgrade children of current node in the tree
        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$depthDbColumn . '` = `' . self::$depthDbColumn . '` - 1, ' .
            '`' . self::$rightDbColumn . '` = `' . self::$rightDbColumn . '` - 1, ' .
            '`' . self::$leftDbColumn . '` = `' . self::$leftDbColumn . '` - 1 ' .
            'WHERE `' . self::$leftDbColumn . '` > :left AND `' . self::$rightDbColumn . '` < :right ' .
            'AND `' . self::$primaryDbColumn . '` != :id';
        $model->getWriteConnection()->query($query, [
            'right' => $currentModel->readAttribute(self::$rightKey),
            'left' => $currentModel->readAttribute(self::$leftKey),
            'id' => $model->readAttribute(self::$primaryKey),
        ]);

        // update parent for immediate sub-nodes of current category
        $query = 'UPDATE `' . $model->getSource() . '` SET' .
            ' `' . self::$parentDbColumn . '` = :parent ' .
            'WHERE `' . self::$parentDbColumn . '` = :id';
        $model->getWriteConnection()->query($query, [
            'id' => $currentModel->readAttribute(self::$primaryKey),
            'parent' => $currentModel->readAttribute(self::$parentKey)
        ]);

        // update nodes on the right of the tree
        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$rightDbColumn . '` = `' . self::$rightDbColumn . '` - 2 ' .
            'WHERE `' . self::$rightDbColumn . '` > :right AND `' . self::$primaryDbColumn . '` != :id';
        $model->getWriteConnection()->query($query, [
            'right' => $currentModel->readAttribute(self::$rightKey),
            'id' => $model->readAttribute(self::$primaryKey),
        ]);

        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$leftDbColumn . '` = `' . self::$leftDbColumn . '` - 2 ' .
            'WHERE `' . self::$leftDbColumn . '` > :right AND `' . self::$primaryDbColumn . '` != :id';
        $model->getWriteConnection()->query($query, [
            'right' => $currentModel->readAttribute(self::$rightKey),
            'id' => $model->readAttribute(self::$primaryKey),
        ]);

        $maxRight = $model::maximum(['column' => self::$rightKey]);
        // unset the parent - make the node a root
        if (!$parentModel) {
            $model->assign([
                self::$leftKey => $maxRight + 1,
                self::$rightKey => $maxRight + 2,
                self::$depthKey => 0,
                self::$parentKey => 0,
            ]);

            return;
        }

        $right = $parentModel->readAttribute(self::$rightKey);
        $depth = $parentModel->readAttribute(self::$depthKey);
        if ($parentModel->readAttribute(self::$rightKey) > $currentModel->readAttribute(self::$rightKey)
            && $parentModel->readAttribute(self::$rightKey) > $currentModel->readAttribute(self::$leftKey)) {
            $right = $parentModel->readAttribute(self::$rightKey) - 2;
            $depth = $parentModel->readAttribute(self::$depthKey) + 1;
        } elseif ($parentModel->readAttribute(self::$rightKey) < $currentModel->readAttribute(self::$rightKey)
            && $parentModel->readAttribute(self::$rightKey) > $currentModel->readAttribute(self::$leftKey)) {
            $right = $parentModel->readAttribute(self::$rightKey) - 1;
            $depth = $parentModel->readAttribute(self::$depthKey);
        }

        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$rightDbColumn . '` = `' . self::$rightDbColumn . '` + 2 ' .
            'WHERE `' . self::$rightDbColumn . '` >= :right';
        $model->getWriteConnection()->query($query, [
            'right' => $right,
        ]);

        $query = 'UPDATE `' . $model->getSource() . '` SET ' .
            '`' . self::$leftDbColumn . '` = `' . self::$leftDbColumn . '` + 2 ' .
            'WHERE `' . self::$leftDbColumn . '` >= :right';
        $model->getWriteConnection()->query($query, [
            'right' => $right,
        ]);

        $model->assign([
            self::$leftKey => $right,
            self::$rightKey => $right + 1,
            self::$depthKey => $depth,
            self::$parentKey => $parentModel->readAttribute(self::$primaryKey),
        ]);
    }
}
