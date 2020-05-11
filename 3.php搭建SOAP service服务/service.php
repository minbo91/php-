<?php
require_once ("lib/nusoap.php");
$server = new soap_server ();
// 避免乱码
$server->soap_defencoding = 'UTF-8';
$server->decode_utf8 = false;
$server->xml_encoding = 'UTF-8';
$server->configureWSDL ('test'); // 打开 wsdl 支持
/*
注册需要被客户端访问的程序
类型对应值： bool->"xsd:boolean"    string->"xsd:string"
int->"xsd:int"     float->"xsd:float"
*/
$server->register ( 'GetTestStr', // 方法名
    //array ("name" => "xsd:string", "passwd" => "xsd:string", "email" => "xsd:string", "age" => "xsd:string"), // 参数，默认为 "xsd:string"
    array ("name" => "xsd:string","data"=>"xsd:string"), // 参数，默认为 "xsd:string"
    //array ("return" => "xsd:string" ) ); // 返回值，默认为 "xsd:string"
    array('return'=>'tns:entry_value')); // 返回值，默认为 "xsd:string"
;

// 'entry_value',
//            'complexType',
//            'struct',
//            'all',
//            '',

$server->wsdl->addComplexType(
    'name_value',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'name'=>array('name'=>'name', 'type'=>'xsd:string'),
        'value'=>array('name'=>'value', 'type'=>'xsd:string'),
    )
);

$server->wsdl->addComplexType(
    'name_value_list',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref'=>'SOAP-ENC:arrayType', 'wsdl:arrayType'=>'tns:name_value[]')
    ),
    'tns:name_value'
);

$elements=          array(
                    'code'=>array('name'=>'code', 'type'=>'xsd:string'),
                    'msg'=>array('name'=>'msg', 'type'=>'xsd:string'),
                    'data'=>array('name'=>'data', 'type'=>'xsd:string'),
                    'name_value_list'=>array('name'=>'name_value_list', 'type'=>'tns:name_value_list'),
                );

$server->wsdl->addComplexType('entry_value', 'complexType', 'struct', 'all', '', $elements);

//isset  检测变量是否设置
$post_data = file_get_contents("php://input");

$post_data = isset ( $post_data  ) ? $post_data : '';

//service  处理客户端输入的数据
$server->service ( $post_data );


/**
 * 供调用的方法
 * @param $name
 */
function GetTestStr($name,$data) {
    //return "Hello, ".$name."!";
    //$data = ['name'=>get_name_value('name','minbo'),'age'=>get_name_value('age','20')];
    //$name_value_list['user_name'] = get_name_value('user_name','minbo@qq.com');
    //$name_value_list['user_age'] = get_name_value('user_age','20');
    //示例报文
    //<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
    //   <soapenv:Header/>
    //   <soapenv:Body>
    //      <GetTestStr soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
    //         <name xsi:type="xsd:string">name</name>
    //         <data xsi:type="xsd:string"><![CDATA[<person><name>minbo</name><age>18</age></person>]]></data>
    //      </GetTestStr>
    //   </soapenv:Body>
    //</soapenv:Envelope>
    $name_value_list = array();
    $data_str =  simplexml_load_string(str_ireplace('&', ' ', $data));
    $data_arr = json_decode(json_encode($data_str), TRUE);
    file_put_contents('01.txt',json_encode($data_arr).PHP_EOL,FILE_APPEND);

    $resp_data = "";
    return ['code'=>'S','msg'=>'success','data'=>$resp_data,'name_value_list'=>$name_value_list];
}
 function get_name_value($field, $value)
{
    return array('name' => $field, 'value' => $value);
}