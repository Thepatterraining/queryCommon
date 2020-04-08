<?php

namespace QueryCommon;

use App\Http\Controllers\Controller;
use App\Http\Models\Admin\TypeDetailModel;
use Illuminate\Http\Request;
use QueryCommon\Filters\WithWheres;
use QueryCommon\Inter\IQuery;
use QueryCommon\Utils\Success;

class QueryController extends Controller implements WithWheres, IQuery
{

    use Success;

    protected $pageIndex;
    protected $pageSize;
    protected $search = [];

    /**
     * 字典数组
     * ['表里的字段名' => '字典code',...]
     */
    protected $dicArr = [];

    /**
     * 字段映射
     * ['搜索字段' => '表字段',...]
     */
    protected $filedsAdapter = [];

    /**
     * 创建时候的字段映射
     * ['输入字段' => '表字段']
     */
    protected $createAdapter = [];

    //定义表名 格式: table as t
    protected $shortTableName;

    //sql语句
    private $sql;

    //数量
    private $count;

    //查询出来的数据
    private $data;

    const LIKE = 'like';
    const ORWHERE = 'or';

    public function __construct(Request $request)
    {
        try {
            parent::__construct($request);
            $this->request = $request;
        } catch (Exception $e) {

        }

    }

    protected function getModel()
    {}

    /**
     * 验证器
     */
    protected function valid(array $rules = [], array $messages = [])
    {
        $rules = count($rules) > 0 ? $rules : $this->rules;
        $messages = count($messages) > 0 ? $messages : $this->messages;
        $validator = Validator::make($this->request->all(), $rules, $messages);
        if ($validator->fails()) {
            $arr = [
                'code' => config('error.valid_code'),
                'msg' => $validator->errors()->first(),
            ];
            return response()->json(json_encode($arr));
        }
    }

    private function pageValid()
    {
        //检测页码和每页数量
        $rules = [
            'page' => 'required',
            'pageSize' => 'required',
        ];
        $messages = [
            'page.required' => '页码为必填项',
            'pageSize.required' => '每页数量为必填项',
        ];
        $this->valid($this->request, $rules, $messages);
        //检测通过把页码和每页数量赋值
        $this->pageIndex = $this->request->input('page');
        $this->pageSize = $this->request->input('pageSize');

        //搜索条件
        if ($this->request->has('search')) {
            $this->search = json_decode($this->request->input('search'), true);
        }
    }

    /**
     * 查询列表
     */
    public function queryList()
    {
        $this->validBefore($this->request);
        $this->pageValid();

        $res = $this->pageList();
        $this->listAfter($res);
        return $this->sucess($res);
    }

    protected function validBefore($request)
    {
    }
    protected function listAfter($res)
    {

    }

    public function getWheres(array $search): array
    {
        //默认大写转下划线， 如果有字段映射使用映射
        $where = [];
        foreach ($search as $col => $key) {
            if (array_key_exists($col, $this->filedsAdapter)) {
                $where[$this->filedsAdapter[$col]] = $key;
            } else {
                $where[snake_case($col)] = $key;
            }
        }
        return $where;
    }

    /**
     * [$key=>$value] = where($key, $value)
     * [$key=>['like',$value]] = where($key, 'like', "%$value%")
     * [$key=>['<|<=|>|>=|=|!='], $value] = where($key, '<|<=|>|>=|=|!=', $value)
     *
     * [$key=>['or',$key]] = orWhere($key, $value)
     * [$key=>['or', ['like',$value]]] = orWhere($key, 'like', "%$value%")
     * [$key=>['or',['<|<=|>|>=|=|!=', $value]] = orWhere($key, '<|<=|>|>=|=|!=', $value)
     *
     */
    private function getQueryable(): void
    {
        $modelStr = $this->getModel();
        $tempSql = new $modelStr;
        //定义表名简写
        if (!empty($this->shortTableName)) {
            $tempSql = $tempSql->from($this->shortTableName);
        }
        //判断有没有条件
        if (!($this instanceof WithWheres)) {
            $this->sql = $tempSql;
        }
        $where = $this->getWheres($this->search);
        $compareOP = ["<", "<=", ">", ">=", "=", '!='];
        if (count($where) > 0) {
            foreach ($where as $col => $val) {
                if (is_array($val)) {
                    if (in_array($val[0], $compareOP)) {
                        $tempSql = $tempSql->where($col, $val[0], $val[1]);
                    } else if ($val[0] == self::LIKE) {
                        $tempSql = $tempSql->where($col, $val[0], "%" . $val[1] . "%");
                    } else if ($val[0] == self::ORWHERE) {
                        //使用orWhere
                        if (is_array($val[1])) {
                            $orWhereArr = $val[1];
                            if (in_array($orWhereArr[0], $compareOP)) {
                                $tempSql = $tempSql->orWhere($col, $orWhereArr[0], $orWhereArr[1]);
                            } elseif ($orWhereArr[0] == self::LIKE) {
                                $tempSql = $tempSql->orWhere($col, $orWhereArr[0], "%" . $orWhereArr[1] . "%");
                            }
                        } else {
                            $tempSql = $tempSql->orWhere($col, $val[1]);
                        }
                    } else {
                        $tempSql = $tempSql->whereBetween($col, $val);
                    }
                } else {
                    $tempSql = $tempSql->where($col, $val);
                }
            }
        }

        $this->sql = $tempSql;
    }

    private function join(): void
    {
        if ($this instanceof WithJoins) {
            $joins = $this->getJoins();
            foreach ($joins as $join) {
                $this->sql = $this->sql->leftJoin($join[0], $join[1], $join[2], $join[3]);
            }
        }
    }

    private function orderBy(): void
    {
        //排序
        if ($this instanceof WithOrderBy) {
            $orderBy = $this->getOrderBy();
            if (count($orderBy) > 0) {
                foreach ($orderBy as $key => $value) {
                    $this->sql = $this->sql->orderBy($key, $value);
                }
            }
        }
    }

    //处理字典
    private function dic()
    {
        foreach ($this->data as $data) {
            if (property_exists($this, 'dicArr')) {
                foreach ($this->dicArr as $col => $dic) {
                    if (array_key_exists($col, $data->toArray())) {
                        if (empty($data->$col) && $data->$col != 0) {
                            $data->$col = ['code' => '000000', 'name' => $data->$col];
                        } else {
                            $dics = TypeDetailModel::getDetailsByCode($dic);
                            if (array_key_exists($data->$col, $dics)) {
                                $data->$col = $dics[$data->$col];
                            } else {
                                $data->$col = ['code' => '000000', 'name' => $data->$col];
                            }
                        }

                    }
                }
            }
        }
    }

    private function result(): array
    {
        $pageIndex = $this->pageIndex;
        $pageSize = $this->pageSize;
        $pageCount = ceil($this->count / $pageSize); #计算总页面数
        $result = [];
        $result['data'] = $this->data;
        $result['count'] = intval($this->count);
        $result['page'] = intval($pageIndex);
        $result['pageSize'] = intval($pageSize);
        $result['pageCount'] = intval($pageCount);
        return $result;
    }

    /**
     * 查询分页
     */
    public function pageList(): array
    {
        $pageIndex = $this->pageIndex;
        $pageSize = $this->pageSize;
        $offset = ($pageIndex - 1) * $pageSize;

        //where条件
        $this->getQueryable();

        //连表
        $this->join();

        //排序
        $this->orderBy();

        //count
        $this->count = $this->sql->count('1');

        //获取要查询的字段
        $filed = ['*'];
        if ($this instanceof WithFields) {
            $filed = $this->getFields();
        }
        $this->data = $this->sql->offset($offset)->limit($pageSize)->get($filed);

        //处理字典
        $this->dic();

        return $this->result();
    }

    /**
     * 创建数据
     * @param Array $datas 数据
     */
    public function create(array $datas)
    {
        $modelClass = $this->getModel();
        $model = new $modelClass;
        foreach ($datas as $key => $val) {
            $field = $this->createAdapter[$key];
            $model->$field = $val;
        }
        return $model->save();
    }

    /**
     * 更新记录内容
     * @param string $id  数据的id
     * @param array $datas 要更新的数据
     */
    public function update(string $id, array $datas)
    {
        $modelClass = $this->getModel();
        $model = new $modelClass;
        $data = $model->where('id', $id)->first();
        foreach ($datas as $key => $val) {
            $field = $this->createAdapter[$key];
            $data->$field = $val;
        }
        return $data->save();
    }
}
