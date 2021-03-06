# queryCommon

查询公用框架，服务于laravel框架

可快速轻松完成查询列表，查询一条记录

创建一个`controller`，继承`QueryController`, 实现下面的函数即可返回要查询的列表

```php
/*
     * 查询列表
     * @route get.api/lists
     */
    public function getList(){
        try{
            //检查页码，搜索条件等
            $this->pageValid();
            
            //返回数据
            $data = $this->pageList();
            return $this->success($data);
        } catch (Exception $ex) {
            
        }
        
    }
```

通过重写下面的函数来决定主要的`model`

```php

protected function getModel() {
    return new Model;
}

```

为什么是主要的model呢，因为如果需要连表查询，使用left join的话，会以上面的Model为主表，那么怎么使用呢，看下面的例子：

首先需要在你的类里实现`WithJoins`接口

然后重写`getJoins`函数，该函数返回一个二维数组，可以连接多张表进行查询

```php

class Controller extends QueryController implements WithJoins
{
    function getJoins(): array
    {
        return [['表名','主表.字段','=','表名.字段'],...];
    }
}

```

默认查询所有字段，如果需要自定义字段也很简单

首先需要在你的类里实现`WithFields`接口

然后重写`getFields`函数，该函数返回一个字段数组，如果连表查询，需要写成`表名.字段名`的形式

```php

class Controller extends QueryController implements WithFields
{
    function getFields(): array
    {
        return ['字段名','表名.字段名',...];
    }
}

```

这里的列表默认按照时间字段created_at倒序排序，如果需要自定义排序也很简单

首先在你的类里实现`WithOrderBy`接口

然后重写`getOrderBy`函数，返回一个数组，支持多字段排序

```php

class Controller extends QueryController implements WithOrderBy
{
    function getOrderBy(): array
    {
        return ['要排序的字段名' => 'asc|desc',...];
    }
}

```

常见的还有需要搜索查询功能，默认查询是下面这样的：

比如要查询用户名是张三的用户，前端可以传过来

['userName' => '张三']

当然这是精确匹配，如果要模糊查询怎么办呢，前端只需要传过来这样的：

['userName' => ['like', '张三']]

这样前端有什么查询需求，后端都不需要改代码了。

如果想要自定义查询功能，也很简单

首先在你的类里实现`WithWheres`接口

然后重写`getWheres`函数，返回一个数组，数组内容就是laravel where里面使用的数组信息


```php

class Controller extends QueryController implements WithWheres
{
    function getWheres(): array
    {
        return ['字段名' => '查询内容'];
    }
}

```

