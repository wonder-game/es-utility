<?php

namespace WonderGame\EsUtility\Common\CloudLib\Dns;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Dnspod\V20210323\DnspodClient;
use TencentCloud\Dnspod\V20210323\Models\CreateRecordRequest;
use TencentCloud\Dnspod\V20210323\Models\CreateRecordResponse;
use TencentCloud\Dnspod\V20210323\Models\DescribeRecordListRequest;
use TencentCloud\Dnspod\V20210323\Models\DescribeRecordListResponse;
use TencentCloud\Dnspod\V20210323\Models\ModifyRecordRequest;
use TencentCloud\Dnspod\V20210323\Models\ModifyRecordResponse;
use TencentCloud\Dnspod\V20210323\Models\RecordListItem;

/**
 * composer require tencentcloud/dnspod
 * @document https://cloud.tencent.com/document/api/1427/56166
 */
class Tencenet extends Base
{
    /**
     * 密钥可前往https://console.cloud.tencent.com/cam/capi网站进行获取
     * @var string
     */
    protected $SecretId = '';

    protected $SecretKey = '';

    private $endpoint = 'dnspod.tencentcloudapi.com';

    public function list(string $recordType = 'A', int $limit = 100): array
    {
        $cred = new Credential($this->SecretId, $this->SecretKey);

        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint($this->endpoint);

        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        $client = new DnspodClient($cred, '', $clientProfile);

        $req = new DescribeRecordListRequest();
        // A 类，最大limit 3000
        $req->fromJsonString(json_encode(['Domain' => $this->domain, 'RecordType' => 'A', 'Limit' => 3000]));

        /** @var DescribeRecordListResponse $resp */
        $resp = $client->DescribeRecordList($req);

        $lists = $resp->getRecordList();

        $result = [];
        // Item类型参考: https://cloud.tencent.com/document/api/1427/56185#RecordListItem
        /** @var RecordListItem $item */
        foreach ($lists as $item)
        {
            // 修改记录时，需要用到一部分参数
            $result[$item->getName()] = [
                'Name' => $item->getName(),
                'Status' => $item->getStatus(),
                'UpdatedOn' => $item->getUpdatedOn(),

                'RecordId' => $item->getRecordId(),
                'Value' => $item->getValue(),
                'RecordType' => $item->getType(),
                'RecordLine' => $item->getLine(),
            ];
        }
        return $result;
    }

    public function create(array $array)
    {
        $array = array_merge(['RecordType' => 'A', 'RecordLine' => '默认'], $array);

        $cred = new Credential($this->SecretId, $this->SecretKey);
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint($this->endpoint);

        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        $client = new DnspodClient($cred, "", $clientProfile);

        $req = new CreateRecordRequest();
        $params = $this->filter($array, ['SubDomain', 'Value', 'RecordType', 'RecordLine']);
        $req->fromJsonString(json_encode(['Domain' => $this->domain] + $params));

        /** @var CreateRecordResponse $resp */
        $resp = $client->CreateRecord($req);

        return $resp->getRecordId();
    }

    public function modify(array $array)
    {
        $array = array_merge(['RecordType' => 'A', 'RecordLine' => '默认'], $array);

        $cred = new Credential($this->SecretId, $this->SecretKey);
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint($this->endpoint);

        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        $client = new DnspodClient($cred, "", $clientProfile);

        $req = new ModifyRecordRequest();
        $params = $this->filter($array, ['RecordType', 'RecordLine', 'Value', 'RecordId', 'SubDomain']);
        $req->fromJsonString(json_encode(['Domain' => $this->domain] + $params));

        /** @var ModifyRecordResponse $resp */
        $resp = $client->ModifyRecord($req);

        return $resp->getRecordId();
    }

    public function delete(array $array)
    {
        // TODO: Implement delete() method.
    }
}
