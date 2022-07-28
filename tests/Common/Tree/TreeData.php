<?php

namespace Tests\Common\Tree;

use WonderGame\EsUtility\Common\Classes\Tree;

/**
 * php easyswoole phpunit tests/Common/Tree/TreeData.php
 */
class TreeData extends Base
{
    // 完整数据
    public function testFull()
    {
        $Tree = new Tree([
            'data' => $this->data,
        ]);
        $treeData = $Tree->treeData();
        print_r($treeData);
        $this->assertCount(2, $treeData);
    }

    // 2个分支
    public function testFilterIds1()
    {
        $Tree = new Tree([
            'data' => $this->data,
            'filterIds' => [15, 3]
        ]);
        $treeData = $Tree->treeData();
        print_r($treeData);
        $this->assertCount(2, $treeData);
    }

    // 1个分支
    public function testFilterIds2()
    {
        $Tree = new Tree([
            'data' => $this->data,
            'filterIds' => [15, 4]
        ]);
        $treeData = $Tree->treeData();
        print_r($treeData);
        $this->assertCount(1, $treeData);
    }

    // vue-router结构
    public function testRouter()
    {
        $Tree = new Tree([
            'data' => $this->router,
            'isRouter' => true,
        ]);
        $treeData = $Tree->treeData();
        print_r($treeData);
        $this->assertCount(2, $treeData);
    }

    // 只有部分权限的vue-router结构
    public function testRouterAuth()
    {
        $Tree = new Tree([
            'data' => $this->router,
            'filterIds' => [1, 2, 58, 56],
            'isRouter' => true,
        ]);
        $treeData = $Tree->treeData();
        print_r($treeData);
        $this->assertCount(2, $treeData);

        $Tree = new Tree([
            'data' => $this->router,
            'filterIds' => [14],
            'isRouter' => true,
        ]);
        $treeData = $Tree->treeData();
        print_r($treeData);
        $this->assertCount(1, $treeData);
    }
}
