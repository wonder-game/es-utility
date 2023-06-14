<?php

namespace Tests\Common;

use PHPUnit\Framework\TestCase;
use EasySwoole\Http\Request as EsRequest;

/**
 * php easyswoole phpunit tests/Common/Ip.php
 */
class Ip extends TestCase
{
    protected $ip = '157.148.40.57';

    public function testA()
    {
        $EsRequest = new EsRequest();
        $EsRequest->withAddedHeader('x-forwarded-for', 'unknown, 157.148.40.57, 157.148.40.57');
        $EsRequest->withAddedHeader('x-forwarded-for', 'unknown, 157.148.40.58, 157.148.40.58');

        $ip = ip($EsRequest);
        $this->assertEquals($ip, $this->ip);
    }

    public function testB()
    {
        $EsRequest = new EsRequest();
        $EsRequest->withAddedHeader('x-forwarded-for', 'unknown, 157.148.40.57, 157.148.40.57');

        $ip = ip($EsRequest);
        $this->assertEquals($ip, $this->ip);
    }

    public function testC()
    {
        $EsRequest = new EsRequest();
        $EsRequest->withAddedHeader('x-forwarded-for', '157.148.40.57, 157.148.40.57');
        $EsRequest->withAddedHeader('x-forwarded-for', '157.148.40.58, 157.148.40.58');

        $ip = ip($EsRequest);
        $this->assertEquals($ip, $this->ip);
    }

    public function testD()
    {
        $EsRequest = new EsRequest();
        $EsRequest->withAddedHeader('x-forwarded-for', '157.148.40.57, 157.148.40.57');

        $ip = ip($EsRequest);
        $this->assertEquals($ip, $this->ip);
    }

    public function testE()
    {
        $EsRequest = new EsRequest();
        $EsRequest->withAddedHeader('x-forwarded-for', '157.148.40.57');

        $ip = ip($EsRequest);
        $this->assertEquals($ip, $this->ip);
    }

    public function testF()
    {
        $EsRequest = new EsRequest();
        $EsRequest->withAddedHeader('x-forwarded-for', '');
        $EsRequest->withAddedHeader('x-forwarded-for', 'unknown, 157.148.40.57, 157.148.40.57');

        $ip = ip($EsRequest);
        $this->assertEquals($ip, $this->ip);
    }
}
