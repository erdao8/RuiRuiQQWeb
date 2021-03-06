<?php
// *   This program is free software: you can redistribute it and/or modify
// *   it under the terms of the GNU General Public License as published by
// *   the Free Software Foundation, either version 3 of the License, or
// *   (at your option) any later version.
// *
// *   This program is distributed in the hope that it will be useful,
// *   but WITHOUT ANY WARRANTY; without even the implied warranty of
// *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// *   GNU General Public License for more details.
// *
// *   You should have received a copy of the GNU General Public License
// *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
// *
// * @author     Xianglong He
// * @copyright  Copyright (c) 2015 Xianglong He. (http://tec.hxlxz.com)
// * @license    http://www.gnu.org/licenses/     GPL v3
// * @version    1.0
// * @discribe   RuiRuiQQ语库添加词条
?>
<?php
//解决变量未定义报错
function _rowget($str,$row)
{
    $val = !empty($row[$str]) ? $row[$str] : null;
    return $val;
}
//屏蔽部分错误信息
error_reporting(E_ALL ^ E_DEPRECATED ^ E_NOTICE);
require 'config.php';

if($_REQUEST['password'] != $AdminPass)
{
    exit("Wrong Password");
}

if($OCSServer!="NONE")
{
    $connect = new Memcache; //声明一个新的memcached链接
    $connect->addServer($OCSServer, 11211);//添加实例地址  端口号
}
//连接数据库
require 'database.php';

//获取提交的QQ号的信息
$sql = "SELECT * FROM qqinf WHERE qq = '$_REQUEST[qqnum]' limit 1";
$result = $mysqli->query($sql);
$row = mysqli_fetch_array($result);
if($row != "")
{
    $qqconf = $row['conf'];   
    if($qqconf == 2)
    {
        $mysqli->close();
        exit("IDDisabled");
    }
}
else $qqconf = 0;
if($_REQUEST[superstudy]=="true")
{
    if($row == "")
    {
        $mysqli->close();
        exit("NotSuper");
    }  
    $qqsuper = $row['super'];
    if($qqsuper != 1)
    {
        $mysqli->close();
        exit("NotSuper");
    }
}
$WaittingFlag = 0;
$aimsql = $mysqli->real_escape_string($_REQUEST[aim]);
$sourcesql = $mysqli->real_escape_string($_REQUEST[source]);
$sourceunsql = $_REQUEST[source];
//写入回复语句并获取编号
$sql = "SELECT * FROM data WHERE data = '$aimsql'  limit 1";
$result = $mysqli->query($sql);
$row = mysqli_fetch_array($result);
if($row == "")
{
    $sql = "INSERT INTO data (data) VALUES ('$aimsql')";
    if (!$mysqli->query($sql))
    {
        $mysqli->close();
        die('Error: ' . $mysqli->error);
    }
    $sql = "SELECT * FROM data WHERE data = '$aimsql'  limit 1";
    $result = $mysqli->query($sql);
    $row = mysqli_fetch_array($result);   
}
$aimno = _rowget('no', $row);
if($OCSServer!="NONE")
{
    $connect->set('SmartQQRobotData1_'.$aimno,_rowget('data', $row),0);
}
//寻找是否存在原语句
$no = -1;
$sql = "SELECT * FROM talk WHERE source = '$sourcesql'  limit 1";
$result = $mysqli->query($sql);
$row = mysqli_fetch_array($result);
if($row != "")
{
    $aim = _rowget('aim', $row);
    $no = _rowget('no', $row);    
    $str = explode(",",$aim);
    $enable = explode(",",$row['enable']);
    
    for($i = 0; $i<count($str);$i ++)
    {
        if($str[$i] == $aimno)
        {
            
            if($enable[$i] == 1 || $enable[$i] == 3)
            {
                $mysqli->close();
                die('Already');
            }
            else if($enable[$i] == 2)
            {
                $mysqli->close();
                die('Forbidden');
            }
            else 
            {
                $WaittingFlag = 1;
                $WaittingIndex = $i;
            }
        }
    }
    if($WaittingFlag == 0)
    {
        //向数据库添加数据
        $sql = "update talk set aim = '$aim,$aimno' where no = $no";
        if($OCSServer!="NONE")
        {
            $connect->set('SmartQQRobotTalk1_'.$sourceunsql,$aim.','.$aimno,0);
        }
        if (!$mysqli->query($sql))
        {
            $mysqli->close();
            die('Error: ' . $mysqli->error);
        }
    }
}
else
{
    $sql = "INSERT INTO talk (source, aim, enable) VALUES ('$sourcesql', '$aimno', '0')";
    if($OCSServer!="NONE")
    {
        $connect->set('SmartQQRobotTalk1_'.$sourceunsql,$aimno,0);
    }
    $result = $mysqli->query($sql);
    echo $mysqli->error;
    if (!$result)
    {
        $mysqli->close();
        die('Error: ' . $mysqli->error);
    }
}

if($no == -1)
{
    $sql = "SELECT * FROM talk WHERE source = '$sourcesql' limit 1";
    $result = $mysqli->query($sql);
    $row = mysqli_fetch_array($result);   
    $no = $row['no'];
}
//写入日志
$sql = "INSERT INTO logstudy (source, aim, qqnum, qunnum, sourceno, aimno) VALUES ('$sourcesql', '$aimsql', '$_REQUEST[qqnum]', '$_REQUEST[qunnum]', '$no', '$aimno')";
if (!$mysqli->query($sql))
{
    $mysqli->close();
    die('Error: ' . $mysqli->error);
}
//按QQ号做记录
$sql = "SELECT * FROM logqqcount WHERE qqnum = '$_REQUEST[qqnum]'  limit 1";
$result = $mysqli->query($sql);
$row = mysqli_fetch_array($result);
if($row == "")   
{   
    $sql = "INSERT INTO logqqcount (qqnum, study) VALUES ('$_REQUEST[qqnum]', 1)";
    $mysqli->query($sql);
}
else
{
    $temp = $row[study] + 1;
    $sql = "UPDATE logqqcount set study = $temp WHERE qqnum = '$_REQUEST[qqnum]'";
    $mysqli->query($sql);
}
//按群号做记录
if($_REQUEST[qunnum] != "NULL")
{
    $sql = "SELECT * FROM logquncount WHERE qunnum = '$_REQUEST[qunnum]'  limit 1";
    $result = $mysqli->query($sql);
    $row = mysqli_fetch_array($result);
    if($row == "")   
    {
        $sql = "INSERT INTO logquncount (qunnum, study) VALUES ('$_REQUEST[qunnum]', 1)";
        $mysqli->query($sql);
    }
    else
    {
        $temp = $row[study] + 1;
        $sql = "UPDATE logquncount set study = $temp WHERE qunnum = '$_REQUEST[qunnum]'";
        $mysqli->query($sql);
    }
}
//如果提交QQ号在白名单，自动通过审核
if($qqconf == 1)
{
    $sql = "SELECT * FROM talk where no = $no ";
    $result = $mysqli->query($sql);
    $row = mysqli_fetch_array($result);
    $aim1 = explode(",",$row['aim']);
    $enable = explode(",",$row['enable']);
    
    if($WaittingFlag == 0)
        $enable[count($aim1)-1] = 3;
    else
        $enable[$WaittingIndex] = 3;    
    
    $enablestr = $enable[0];
    for($i=1; $i < count($aim1); $i++)
    {
        if($enable[$i] < 0 || $enable[$i] > 10)
            $enable[$i] = 0;
        $enablestr = $enablestr.",".$enable[$i];      
    }
    $sql = "update talk set enable = '$enablestr' where no = $no";
    if($OCSServer!="NONE")
    {
        $connect->set('SmartQQRobotTalk1Enable_'.$_REQUEST[sourceno],$enablestr,0);
    }
    
    if (!$mysqli->query($sql))
    {
        $mysqli->close();
        die('Error: ' . $mysqli->error);
    }
    else
    {
        $mysqli->close();
        die('Success');
    }
}
    
$mysqli->close();
die('Waitting');

?>