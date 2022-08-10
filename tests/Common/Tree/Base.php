<?php

namespace Tests\Common\Tree;

use PHPUnit\Framework\TestCase;

abstract class Base extends TestCase
{
    protected $data = [
        [
            'id' => 25,
            'pid' => 15,
            'path' => '25'
        ],
        [
            'id' => 15,
            'pid' => 10,
            'path' => '15'
        ],
        [
            'id' => 16,
            'pid' => 10,
            'path' => '16'
        ],
        [
            'id' => 10,
            'pid' => 4,
            'path' => '10'
        ],
        [
            'id' => 11,
            'pid' => 4,
            'path' => '11'
        ],
        [
            'id' => 3,
            'pid' => 1,
            'path' => '3'
        ],
        [
            'id' => 1,
            'pid' => 0,
            'path' => '/1'
        ],
        [
            'id' => 4,
            'pid' => 0,
            'path' => '/4'
        ],
        [
            'id' => 26,
            'pid' => 15,
            'path' => '26'
        ],
    ];

    protected $router = [
        [
            'id' => 1,
            'pid' => 0,
            'type' => 0,
            'name' => 'System',
            'title' => '系统管理',
            'sort' => 99,
            'icon' => 'ion:settings-outline',
            'path' => '/system',
            'component' => 'LAYOUT',
            'redirect' => '/system/account',
            'status' => 1,
            'permission' => '',
            'isext' => 0,
            'isshow' => 1,
            'keepalive' => 1,
            'affix' => 0,
            'breadcrumb' => 1,
        ],
        [
            'id' => 2,
            'pid' => 1,
            'type' => 1,
            'name' => 'AccountManagement',
            'title' => '账号管理',
            'sort' => 9,
            'icon' => '',
            'path' => 'account',
            'component' => '/admin/system/account/index',
            'redirect' => '',
            'status' => 1,
            'permission' => '/admin/index',
            'isext' => 0,
            'isshow' => 1,
            'keepalive' => 1,
            'affix' => 0,
            'breadcrumb' => 1,
        ],
        [
            'id' => 14,
            'pid' => 2,
            'type' => 2,
            'name' => '',
            'title' => '新增账号',
            'sort' => 9,
            'icon' => '',
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'permission' => '/admin/add',
            'isext' => 0,
            'isshow' => 1,
            'keepalive' => 1,
            'affix' => 0,
            'breadcrumb' => 1,
        ],
        [
            'id' => 56,
            'pid' => 0,
            'type' => 0,
            'name' => 'Dashboard',
            'title' => '首页',
            'sort' => 1,
            'icon' => 'ant-design:appstore-outlined',
            'path' => '/dashboard',
            'component' => 'LAYOUT',
            'redirect' => '/dashboard/analysis',
            'status' => 1,
            'permission' => '',
            'isext' => 0,
            'isshow' => 1,
            'keepalive' => 1,
            'affix' => 0,
            'breadcrumb' => 1,
        ],
        [
            'id' => 57,
            'pid' => 56,
            'type' => 1,
            'name' => 'Analysis',
            'title' => '分析页',
            'sort' => 1,
            'icon' => '',
            'path' => 'analysis',
            'component' => '/dashboard/analysis/index',
            'redirect' =>'',
            'status' => 1,
            'permission' => '/admin/dashboardAnalysis',
            'isext' => 0,
            'isshow' => 1,
            'keepalive' => 1,
            'affix' => 1,
            'breadcrumb' => 1,
        ],
        [
            'id' => 58,
            'pid' => 56,
            'type' => 1,
            'name' => 'Workbench',
            'title' => '工作台',
            'sort' => 9,
            'icon' =>'',
            'path' => 'workbench',
            'component' => '/dashboard/workbench/index',
            'redirect' =>'',
            'status' => 1,
            'permission' => '/admin/dashboardWorkbench',
            'isext' => 0,
            'isshow' => 1,
            'keepalive' => 1,
            'affix' => 1,
            'breadcrumb' => 1,
        ]
    ];
}
