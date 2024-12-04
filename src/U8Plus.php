<?php
/**
 * 作者:郭磊
 * 邮箱:174000902@qq.com
 * 电话:15210720528
 * Git:https://github.com/guolei19850528/laravel-chanjet
 */

namespace Guolei\Laravel\Yongyou\Library\Chanjet\U8plus\Pfs;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

/**
 * U8plus物业收费系统 API
 */
class U8Plus
{
    /**
     * api url
     * @var string
     */
    protected string $url = '';

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): U8Plus
    {
        $this->url = $url;
        return $this;
    }


    public function __construct(string $url = '')
    {
        $this->setUrl($url);
    }

    /**
     * @param array $data
     * @param string $filterKey
     * @param array|Collection|null $options
     * @param \Closure|null $closure
     * @return array|Collection|null
     */
    public function getDataSet(
        array                 $data = [],
        string                $filterKey = 'soap:Body.GetDataSetResponse.GetDataSetResult.diffgr:diffgram.NewDataSet.Table',
        array|Collection|null $options = [],
        \Closure              $closure = null
    ): array|Collection|null
    {
        $_data = [
            'soap:Body' => [
                'GetDataSet' => [
                    '_attributes' => [
                        'xmlns' => 'http://zkhb.com.cn/'
                    ],
                    ...$data
                ]
            ]
        ];

        $xmlString = ArrayToXml::convert(
            $_data,
            [
                'rootElementName' => 'soap:Envelope',
                '_attributes' => [
                    'xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
                    'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
                    'xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
                ]
            ],
            true,
            'utf-8',
            '1.0'
        );
        $response = Http::withBody($xmlString, 'text/xml; charset=utf-8')
            ->withOptions(\collect($options)->toArray())
            ->post($this->url);
        if ($closure) {
            return call_user_func($closure, $response);
        }
        if ($response->ok()) {
            $array = XmlToArray::convert($response->body());
            return \data_get($array, $filterKey, []);
        }
        return [];
    }

    /**
     * 按条件查询实际收费列表
     * @param string $condition 查询条件
     * @param string $filterKey 筛选Key
     * @param array|Collection|null $options Http options
     * @param \Closure|null $closure
     * @return array|Collection|null
     */
    public function queryActualPaymentReceivedItemsByCondition(
        string                $condition = '',
        string                $filterKey = 'soap:Body.GetDataSetResponse.GetDataSetResult.diffgr:diffgram.NewDataSet.Table',
        array|Collection|null $options = [],
        \Closure              $closure = null
    ): array|Collection|null
    {
        $sql = sprintf("select
            cml.ChargeMListID,
            cml.ChargeMListNo,
            cml.ChargeTime,
            cml.PayerName,
            cml.ChargePersonName,
            cml.ActualPayMoney,
            cml.EstateID,
            cml.ItemNames,
            ed.Caption as EstateName,
            cfi.ChargeFeeItemID,
            cfi.ActualAmount,
            cfi.SDate,
            cfi.EDate,
            cfi.RmId,
            rd.RmNo,
            cml.CreateTime,
            cml.LastUpdateTime,
            cbi.ItemName,
            cbi.IsPayFull
        from
            chargeMasterList cml,EstateDetail ed,ChargeFeeItem cfi,RoomDetail rd,ChargeBillItem cbi
        where
            cml.EstateID=ed.EstateID
            and
            cml.ChargeMListID=cfi.ChargeMListID
            and
            cfi.RmId=rd.RmId
            and
            cfi.CBillItemID=cbi.CBillItemID
            %s
        order by cfi.ChargeFeeItemID desc;", $condition);
        return $this->getDataSet(['sql' => $sql], $filterKey, $options, $closure);
    }

    /**
     * 查询实际收费列表
     * @param string|int $estateId 项目ID
     * @param string $chargeType 收费类型
     * @param string $roomNo 房间号
     * @param string|null $endDate 结束日期
     * @param string $filterKey 过滤key
     * @param array|Collection $options Guzzle options
     * @return array|Collection|null
     */
    public function queryActualPaymentReceivedItems(
        string|int            $estateId = 0,
        string                $chargeType = '',
        string                $roomNo = '',
        string|null           $endDate = null,
        string                $filterKey = 'soap:Body.GetDataSetResponse.GetDataSetResult.diffgr:diffgram.NewDataSet.Table',
        array|Collection|null $options = [],
        \Closure              $closure = null
    ): array|Collection|null
    {
        $condition = sprintf(" and (cml.EstateID=%s and cbi.ItemName='%s' and rd.RmNo='%s',and cfi.EDate>='%s') order by cfi.ChargeFeeItemID desc;", $estateId, $chargeType, $roomNo, $endDate);
        return $this->queryActualPaymentReceivedItemsByCondition($condition, $filterKey, $options, $closure);
    }
}
