<?php

namespace App\Http\Utils;

use App\Models\Sys\ErrorModel;

trait Errors {

    function error($code) {
        $error = ErrorModel::where('code', $code)->first(['msg']);
        // dd(empty($error));
        if (empty($error)) {
            $res = ['code'=>'10000','msg'=>'系统错误，错误代码未定义'];
        } else {
            $res = ['code'=>$code,'msg'=>$error->msg];
        }

        return response()->json($res);
    }
}
