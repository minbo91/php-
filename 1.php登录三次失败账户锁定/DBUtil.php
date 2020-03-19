<?php


class DBUtil{
    public static $config = array();//设置连接参数，配置信息
    public static $link = null;//保存连接标识符
    public static $pconnect = false;//是否开启长连接
    public static $dbVersion = null;//保存数据库版本
    public static $connected = false;//判断是否连接成功
    public static $PDOStatement = null;//保证PDOStatement对象
    public static $queryStr = null;//保存最后执行的操作
    public static $error = null;//保存错误信息
    public static $lastInsertId = null;//保存上一步插入操作保存的AUTO_INCREMANT
    public static $numRows = null;//受影响记录的条数
    /**
     * 构造函数，连接数据库
     *
     * @param   array|string $dbConfig The database configuration
     *
     * @return   boolean    ( description_of_the_return_value )
     */
    public function __construct($dbConfig=''){
        if(!class_exists("PDO")){
            self::throw_exception("不支持PDO,请先开启");
        }
        if(!is_array($dbConfig)){
            $dbConfig = array(
                'hostname' => 'localhost',
                'username' => 'root',
                'password' => 'root',
                'database' => 'test',
                'hostport' => '3306',
                'dbms'   => 'mysql',
                'dsn'   => 'mysql:host=localhost;dbname=test'
            );
        }
        if(empty($dbConfig['hostname'])){
            self::throw_exception("没有定义数据库配置,请先定义");
        }
        self::$config = $dbConfig;
        if(empty(self::$config['params'])){
            self::$config['params'] = array();
        }
        if(!isset(self::$link)){
            $configs = self::$config;
            if(self::$pconnect){
                //开启长连接,添加到配置数组中
                $configs['params'][constant("PDO::ATTR_PERSISTENT")] = true;
            }
            try {
                self::$link = new PDO($configs['dsn'],$configs['username'],$configs['password'],$configs['params']);
            } catch (PDOException $e) {
                self::throw_exception($e->getMessage());
            }
            if(!self::$link){
                self::throw_exception("PDO连接错误");
                return false;
            }
            self::$link->exec("set names utf8");
            self::$dbVersion = self::$link->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
            unset($configs);
        }
    }
    /**
     * 得到所有记录
     *
     * @param   <type> $sql  The sql
     *
     * @return   <type> All.
     */
    public static function getAll($sql=null){
        if($sql!=null){
            self::query($sql);
        }
        $result = self::$PDOStatement->fetchAll(constant("PDO::FETCH_ASSOC"));
        return $result;
    }
    /**
     * 得到一条记录
     *
     * @param   <type> $sql  The sql
     *
     * @return   <type> The row.
     */
    public static function getRow($sql=null){
        if($sql!=null){
            self::query($sql);
        }
        $result = self::$PDOStatement->fetch(constant("PDO::FETCH_ASSOC"));
        return $result;
    }
    /**
     * 执行增删改操作，返回受影响记录的条数
     *
     * @param   <type>  $sql  The sql
     *
     * @return   boolean ( description_of_the_return_value )
     */
    public static function execute($sql=null){
        $link = self::$link;
        if(!$link)return false;
        if($sql!=null){
            self::$queryStr = $sql;
        }
        if(!empty(self::$PDOStatement))self::free();
        $result = $link->exec(self::$queryStr);
        self::haveErrorThrowException();
        if($result){
            self::$lastInsertId = $link->lastInsertId();
            self::$numRows = $result;
            return $result;
        }else{
            return false;
        }
    }
    /**
     * 根据主键查找记录
     *
     * @param   <type> $tabName The tab name
     * @param   <type> $priId  The pri identifier
     * @param   string $fields  The fields
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function findById($tabName,$priId,$fields='*'){
        $sql = 'SELECT %s FROM %s WHERE id=%d';
        return self::getRow(sprintf($sql,self::parseFields($fields),$tabName,$priId));
    }
    /**
     * 执行普通查询
     *
     * @param   <type> $tables The tables
     * @param   <type> $where  The where
     * @param   string $fields The fields
     * @param   <type> $group  The group
     * @param   <type> $having The having
     * @param   <type> $order  The order
     * @param   <type> $limit  The limit
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function find($tables,$where=null,$fields='*',$group=null,$having=null,$order=null,$limit
    =null){
        $sql = 'SELECT '.self::parseFields($fields).' FROM '.$tables
            .self::parseWhere($where)
            .self::parseGroup($group)
            .self::parseHaving($having)
            .self::parseOrder($order)
            .self::parseLimit($limit);
        $data = self::getAll($sql);
        return $data;
    }
    /**
     * 添加记录
     *
     * @param   <type> $data  The data
     * @param   <type> $table The table
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function add($data,$table){
        $keys = array_keys($data);
        array_walk($keys, array('PdoMySQL','addSpecialChar'));
        $fieldsStr = join(',',$keys);
        $values = "'".join("','",array_values($data))."'";
        $sql = "INSERT {$table}({$fieldsStr}) VALUES({$values})";
        return self::execute($sql);
    }
    /**
     * 更新数据
     *
     * @param   <type> $data  The data
     * @param   <type> $table The table
     * @param   <type> $where The where
     * @param   <type> $order The order
     * @param   <type> $limit The limit
     */
    public static function update($data,$table,$where=null,$order=null,$limit=null){
        $sets = '';
        foreach ($data as $key => $value) {
            $sets .= $key."='".$value."',";
        }
        $sets = rtrim($sets,',');
        $sql = "UPDATE {$table} SET {$sets}".self::parseWhere($where).self::parseOrder($order).self::parseLimit($limit);
        echo $sql;
    }
    /**
     * 删除数据
     *
     * @param   <type> $data  The data
     * @param   <type> $table The table
     * @param   <type> $where The where
     * @param   <type> $order The order
     * @param   <type> $limit The limit
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function delete($table,$where=null,$order=null,$limit=null){
        $sql = "DELETE FROM {$table} ".self::parseWhere($where).self::parseOrder($order).self::parseLimit($limit);
        return self::execute($sql);
    }
    /**
     * 执行查询
     *
     * @param   string  $sql  The sql
     *
     * @return   boolean ( description_of_the_return_value )
     */
    public static function query($sql=''){
        $link = self::$link;
        if(!$link)return false;
        //判断之前是否有结果集，如果有的话，释放结果集
        if(!empty(self::$PDOStatement))self::free();
        self::$queryStr = $sql;
        self::$PDOStatement = $link->prepare(self::$queryStr);
        $res = self::$PDOStatement->execute();
        self::haveErrorThrowException();
        return $res;
    }
    /**
     * 获取最后执行的sql
     *
     * @return   boolean The last sql.
     */
    public static function getLastSql(){
        $link = self::$link;
        if(!$link){
            return false;
        }
        return self::$queryStr;
    }
    /**
     * 获取最后插入的ID
     *
     * @return   boolean The last insert identifier.
     */
    public static function getLastInsertId(){
        $link = self::$link;
        if(!$link){
            return false;
        }
        return self::$lastInsertId;
    }
    /**
     * 获得数据库的版本
     *
     * @return   boolean The database version.
     */
    public static function getDbVersion(){
        $link = self::$link;
        if(!$link){
            return false;
        }
        return self::$dbVersion;
    }
    /**
     * 得到数据库中表
     *
     * @return   array ( description_of_the_return_value )
     */
    public static function showTables(){
        $tables = array();
        if(self::query("show tables")){
            $result = self::getAll();
            foreach ($result as $key => $value) {
                $tables[$key] = current($value);
            }
        }
        return $tables;
    }
    /**
     * 解析where条件
     *
     * @param   <type> $where The where
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function parseWhere($where){
        $whereStr = '';
        if(is_string($where)&&!empty($where)){
            $whereStr = $where;
        }
        return empty($whereStr) ? '' : ' WHERE '.$whereStr;
    }
    /**
     * 解析group
     *
     * @param   <type> $group The group
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function parseGroup($group){
        $groupStr = '';
        if(is_array($group)){
            $groupStr = implode(',', $group);
        }elseif(is_string($group)&&!empty($group)){
            $groupStr = $group;
        }
        return empty($groupStr) ? '' : ' GROUP BY '.$groupStr;
    }
    /**
     * 解析having
     *
     * @param   <type> $having The having
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function parseHaving($having){
        $havingStr = '';
        if(is_string($having)&&!empty($having)){
            $havingStr = $having;
        }
        return empty($havingStr) ? '' : ' HAVING '.$havingStr;
    }
    /**
     * 解析order
     *
     * @param   <type> $order The order
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function parseOrder($order){
        $orderStr = '';
        if(is_string($order)&&!empty($order)){
            $orderStr = $order;
        }
        return empty($orderStr) ? '' : ' ORDER BY '.$orderStr;
    }
    /**
     * 解析limit
     *
     * @param   <type> $limit The limit
     *
     * @return   <type> ( description_of_the_return_value )
     */
    public static function parseLimit($limit){
        $limitStr = '';
        if(is_array($limit)){
            $limitStr = implode(',', $limit);
        }elseif(is_string($limit)&&!empty($limit)){
            $limitStr = $limit;
        }
        return empty($limitStr) ? '' : ' LIMIT '.$limitStr;
    }
    /**
     * 解析字段
     *
     * @param   <type> $fields The fields
     *
     * @return   string ( description_of_the_return_value )
     */
    public static function parseFields($fields){
        if(is_array($fields)){
            array_walk($fields, array('PdoMySQL','addSpecialChar'));
            $fieldsStr = implode(',', $fields);
        }elseif (is_string($fields)&&!(empty($fields))) {
            if(strpos($fields, '`')===false){
                $fields = explode(',', $fields);
                array_walk($fields, array('PdoMySQL','addSpecialChar'));
                $fieldsStr = implode(',', $fields);
            }else{
                $fieldsStr = $fields;
            }
        }else{
            $fieldsStr = "*";
        }
        return $fieldsStr;
    }
    /**
     * 通过反引号引用字字段
     *
     * @param   string $value The value
     *
     * @return   string ( description_of_the_return_value )
     */
    public static function addSpecialChar(&$value){
        if($value==="*"||strpos($value,'.')!==false||strpos($value,'`')!==false){
            //不用做处理
        }elseif(strpos($value, '`')===false){
            $value = '`'.trim($value).'`';
        }
        return $value;
    }
    /**
     * 释放结果集
     */
    public static function free(){
        self::$PDOStatement = null;
    }
    /**
     * 抛出错误信息
     *
     * @return   boolean ( description_of_the_return_value )
     */
    public static function haveErrorThrowException(){
        $obj = empty(self::$PDOStatement) ? self::$link : self::$PDOStatement;
        $arrError = $obj->errorInfo();
        if($arrError[0]!='00000'){
            self::$error = 'SQLSTATE=>'.$arrError[0].'<br/>SQL Error=>'.$arrError[2].'<br/>Error SQL=>'.self::$queryStr;
            self::throw_exception(self::$error);
            return false;
        }
        if(self::$queryStr==''){
            self::throw_exception('没有执行SQL语句');
            return false;
        }
    }
    /**
     * 自定义错误处理
     *
     * @param   <type> $errMsg The error message
     */
    public static function throw_exception($errMsg){
        echo $errMsg;
    }
    /**
     * 销毁连接对象，关闭数据库
     */
    public static function close(){
        self::$link = null;
    }
}

