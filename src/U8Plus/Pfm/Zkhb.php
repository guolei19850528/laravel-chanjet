<?php
/**
 * 作者:郭磊
 * 邮箱:174000902@qq.com
 * 电话:15210720528
 * Git:https://github.com/guolei19850528/laravel-chanjet
 */

namespace Guolei19850528\Laravel\Chanjet\U8Plus\Pfm;

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
class Zkhb
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

    public function setUrl(string $url): self
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
     * @param string $url
     * @param array|Collection|null $urlParameters
     * @param array|Collection|null $options
     * @param \Closure|null $responseHandler
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
            $filterArray = \data_get($array, $filterKey, []);
            if (\str(\data_get($filterArray, 'ChargeMListID', ''))->isNotEmpty()) {
                return [$filterArray];
            }
            return $filterArray;
        }
        return [];
    }


    /**
     * 按条件查询实际收费列表
     * @param string|null $topColumnString
     * @param string|null $conditionString
     * @param string|null $orderBy
     * @param string $filterKey 筛选Key
     * @param array|Collection|null $options Http options
     * @param \Closure|null $responseHandler
     * @return array|Collection|null
     */
    public function queryActualChargeBillItemList(
        string|null           $topColumnString = null,
        string|null           $conditionString = null,
        string|null           $orderByString = ' order by cfi.ChargeFeeItemID desc',
        string                $filterKey = 'soap:Body.GetDataSetResponse.GetDataSetResult.diffgr:diffgram.NewDataSet.Table',
        array|Collection|null $options = [],
        \Closure|null         $responseHandler = null
    ): array|Collection|null
    {
        $sql = \str('select ')->append(
            $topColumnString,
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
        )->append(' where 1=1', $conditionString)->append($orderByString)->toString();
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
     * 查询实际收费列表条件字符串格式化
     * @param string|int $estateId 项目ID
     * @param string|null $chargeType 收费类型
     * @param string|null $roomNo 房间号
     * @param string|null $endDateBegin 结束日期开始
     * @param string|null $endDateEnd 结束日期结束
     * @return string|null
     */
    public function queryActualChargeBillItemListConditionStringFormatter(
        string|int  $estateId = 0,
        string      $chargeType = null,
        string      $roomNo = null,
        string|null $endDateBegin = null,
        string|null $endDateEnd = null
    ): string|null
    {
        $conditionString = \str(null);
        if (\str($estateId)->isNotEmpty()) {
            $conditionString = $conditionString->append(sprintf(" and cml.EstateID=%s", $estateId));
        }
        if (\str($chargeType)->isNotEmpty()) {
            $conditionString = $conditionString->append(sprintf(" and cbi.ItemName='%s'", $chargeType));
        }
        if (\str($roomNo)->isNotEmpty()) {
            $conditionString = $conditionString->append(sprintf(" and rd.RmNo='%s'", $roomNo));
        }

        if (\str($endDateBegin)->isNotEmpty()) {
            $conditionString = $conditionString->append(sprintf(" and cfi.EDate>='%s'", $endDateBegin));
        }
        if (\str($endDateEnd)->isNotEmpty()) {
            $conditionString = $conditionString->append(sprintf(" and cfi.EDate<='%s'", $endDateEnd));
        }
        return $conditionString->toString();
    }
}
