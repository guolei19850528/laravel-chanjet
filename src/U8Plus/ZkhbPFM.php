<?php
/**
 * 作者:郭磊
 * 邮箱:174000902@qq.com
 * 电话:15210720528
 * Git:https://github.com/guolei19850528/laravel-chanjet
 */

namespace Guolei19850528\Laravel\Chanjet\U8Plus;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

/**
 * U8plus物业收费系统 API
 */
class ZkhbPFM
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

    public function setUrl(string $url): ZkhbPFM
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
        string                $url = '',
        array|Collection|null $urlParameters = [],
        array|Collection|null $options = [],
        \Closure|null         $responseHandler = null
    ): array|Collection|null
    {
        $url = \str($url)->isNotEmpty() ? $url : $this->getUrl();
        $options = \collect($options);
        $urlParameters = \collect($urlParameters);
        $xmlData = [
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
            $xmlData,
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
            ->withOptions($options->toArray())
            ->withUrlParameters($urlParameters->toArray())
            ->post($url);
        if ($responseHandler instanceof \Closure) {
            return \value($responseHandler($response));
        }
        if ($response->ok()) {
            $array = XmlToArray::convert($response->body());
            print_r($array);
            return \data_get($array, $filterKey, []);
        }
        return [];
    }


    /**
     * 按条件查询实际收费列表
     * @param string $conditions
     * @param string $filterKey 筛选Key
     * @param array|Collection|null $options Http options
     * @param \Closure|null $responseHandler
     * @return array|Collection|null
     */
    public function queryActualChargeBillItems(
        string|null           $top = null,
        string|null           $conditions = null,
        string|null           $orderBy = ' order by cfi.ChargeFeeItemID desc',
        string                $filterKey = 'soap:Body.GetDataSetResponse.GetDataSetResult.diffgr:diffgram.NewDataSet.Table',
        array|Collection|null $options = [],
        \Closure|null         $responseHandler = null
    ): array|Collection|null
    {
        $sql = \str('select ')->append(
            $top,
            \collect([
                'cml.ChargeMListID',
                'cml.ChargeMListNo',
                'cml.ChargeTime',
                'cml.PayerName',
                'cml.ChargePersonName',
                'cml.ActualPayMoney',
                'cml.EstateID',
                'cml.ItemNames',
                'ed.Caption as EstateName',
                'cfi.ChargeFeeItemID',
                'cfi.ActualAmount',
                'cfi.SDate',
                'cfi.EDate',
                'cfi.RmId',
                'rd.RmNo',
                'cml.CreateTime',
                'cml.LastUpdateTime',
                'cbi.ItemName',
                'cbi.IsPayFull',
            ])->join(',')
        )->append(
            ...[
                ' from chargeMasterList as cml',
                ' left join EstateDetail as ed on cml.EstateID=ed.EstateID',
                ' left join ChargeFeeItem as cfi on cml.ChargeMListID=cfi.ChargeMListID',
                ' left join RoomDetail as rd on cfi.RmId=rd.RmId',
                ' left join ChargeBillItem as cbi on cfi.CBillItemID=cbi.CBillItemID',
            ]
        )->append($conditions)->append($orderBy)->toString();
        return $this->getDataSet(
            ...\collect([
            'data' => [
                'sql' => $sql,
            ],
            'filterKey' => $filterKey,
            'options' => $options,
            'responseHandler' => $responseHandler
        ])->toArray());
    }

    /**
     * 查询实际收费列表
     * @param string|int $estateId 项目ID
     * @param string $chargeType 收费类型
     * @param string $roomNo 房间号
     * @param string|null $endDate 结束日期
     * @param string $filterKey 过滤key
     * @param array|Collection|null $options Guzzle options
     * @param \Closure|null $responseHandler
     * @return array|Collection|null
     */
    public function queryActualChargeBillItemsConditionsFormatter(
        string|int  $estateId = 0,
        string      $chargeType = null,
        string      $roomNo = null,
        string|null $endDateBegin = null,
        string|null $endDateEnd = null
    ): string|null
    {
        $conditions = \str(null);
        if (Validator::make(['estateId' => $estateId], ['estateId' => 'required|integer|min:1'])->messages()->isEmpty()) {
            $conditions = $conditions->append(sprintf(" and cml.EstateID=%s", $estateId));
        }
        if (Validator::make(['ItemName' => $chargeType], ['ItemName' => 'required|string|min:1'])->messages()->isEmpty()) {
            $conditions = $conditions->append(sprintf(" and cbi.ItemName='%s'", $chargeType));
        }

        dd($conditions);


        $conditions = sprintf(" and (cml.EstateID=%s and cbi.ItemName='%s') order by cfi.ChargeFeeItemID desc", $estateId, $chargeType, $roomNo, $endDateBegin);
        return $conditions;
    }
}
