<?php

namespace Tests\Common\Tree;

use WonderGame\EsUtility\Common\Classes\Tree;

/**
 * php easyswoole phpunit tests/Common/Tree/GetHomePage.php
 */
class GetHomePage extends Base
{
    protected $fullPath = '/system/account';

    public function testFilterIds()
    {
        $Tree = new Tree([
            'data' => $this->router,
            'filterIds' => 2
        ]);
        $homePage = $Tree->getHomePage();
        print_r($homePage);
        $this->assertEquals($this->fullPath, $homePage);
    }

    public function testParams()
    {
        $Tree = new Tree([
            'data' => $this->router,
        ]);
        $homePage = $Tree->getHomePage(2);
        print_r($homePage);
        $this->assertEquals($this->fullPath, $homePage);
    }
}
