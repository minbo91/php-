<?php

//header("content-type:text/html;charset=utf-8");
header("Content-Type: text/html; charset=gb2312");
include_once("DBUtil.php");
/**
 * 登录
 * 1、接收数据
 * 2、正则判断接收到的数据是否合理
 * 3、根据用户名获取用户数据
 *      获取到数据 -> 继续执行
 *      没有获取到数据 -> 提示：用户名密码错误
 * 4、判断锁定时间
 *      当前时间和锁定时间差 大于 10分钟 或者 没有锁定时间 -> 继续执行
 *      当前时间和锁定时间差 小于 10分钟 -> 提示：账号锁定中、请10分钟后再试
 * 5、判断密码
 *      ==
 *          次数=0
 *          登录成功
 *      !=
 *          次数 大于等于 2 -> 锁定操作、次数=0  -> 账号已经锁定
 *          次数 小于 2  次数+1 -> 账号密码错误
 */

 function login($user_name,$user_hash)
{
    $name = $user_name;
    $pwd = $user_hash;
    $arr = [];
    if ( $name == '' || $pwd == '' || $name == null || $pwd == null) {
        $arr['code'] = 1;
        $arr['msg'] = '参数错误、用户名或密码不能为空';
        $arr['data'] = [];
        return $arr;
    }

    $preg_name = '/^[\x{4e00}-\x{9fa5}]{2,5}$/u';
    if( !preg_match( $preg_name, $name ) )
    {
        $arr['code'] = 1;
        $arr['msg'] = '用户名要求必须是2到5位的汉字';
        $arr['data'] = [];
        return $arr;
    }

    $preg_pwd = '/^\S{5,18}$/';
    if (!preg_match($preg_pwd, $pwd)) {
        $arr['code'] = 1;
        $arr['msg'] = '密码要求必须5到18位非空字符串';
        $arr['data'] = [];
        return $arr;
    }
    $where[] = '1=1';
    $where[] = "user_name='{$name}'";
    $whereString = implode(' AND ', $where);

    $db = new DBUtil();

    $sql = "SELECT * FROM hat_asset_locations  WHERE {$whereString}";

    $res = $db::getRow($sql);



    if (!$res) {
        $arr['code'] = 1;
        $arr['msg'] = '用户名或密码错误、请重试';
        $arr['data'] = [];
        return $arr;
    }

    if($res['user_lock_time'] != '' && time() - strtotime($res['user_lock_time']) < 1*60 )
    {
        $arr['code'] = 1;
        $arr['msg'] = '该账号已被锁定、请10分钟后重试';
        $arr['data'] = [];
        return $arr;
    }

    $upd_where[] = "user_i='{$res['user_id']}'";

    $upd_where_string = implode(' AND ', $upd_where);
    if( $pwd != $res['user_pwd_login'] )
    {
        // 次数 大于等于 2 -> 锁定操作、次数=0 -> 账号已经锁定
        if( $res['user_pwd_num'] >= 2 )
        {
            $upd_data['user_lock_time'] = date('Y-m-d H:i:s', time() );
            $upd_data['user_pwd_num'] = 0;
            $db::update($upd_data,'user',$upd_where_string);
            //Db::table('user')->where($upd_where )->update( $upd_data );
            $arr['code'] = 1;
            $arr['msg'] = '账号密码错误次数超过3次、账号锁定10分钟、请稍后重试';
            $arr['data'] = [];
            return $arr;
        }
        else
        {
            $upd_data['user_pwd_num'] = $res['user_pwd_num'] + 1;
            // 次数 小于2 次数+1 -> 账号密码错误
            $db::update($upd_data,'user',$upd_where_string);
            $arr['code'] = 1;
            $arr['msg'] = '账号密码错误、剩余'. (3 - ($res['user_pwd_num'] + 1) ) .'次、请稍后重试';
            $arr['data'] = [];
            return $arr;
        }
    }

    //Db::table('user')->where($upd_where)->update(['user_pwd_num'=>0]);

    $db::update(['user_pwd_num'=>0],'user',$upd_where_string);

    //Session::set('user', $res);

    $arr['code'] = 0;
    $arr['msg'] = '登录成功';
    $arr['data'] = $res;

    $db::close();
    return $arr;
}

$a = login('89921218@qq.com','1');

 //print_r("在APACHE配置文件中有一行是编码的设置 默认的");
 echo "谁发";
