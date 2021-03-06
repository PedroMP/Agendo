<?php
//session_start();	
// This class was altered by Pedro Pires (The chosen two)
	require_once("commonCode.php");
	initSession();
?>


<?php

/*
  @author Nuno Moreno
  @copyright 2009-2010 Nuno Moreno
  @license http://www.gnu.org/copyleft/lesser.html Distributed under the Lesser General Public License (LGPL)
  @version 1.0
  @ ajax request handler
*/

// require_once(".htconnect.php");
require_once("functions.php");

$type=$_GET['type'];

//echo $action;
call_user_func($type);

/**
   * returns resource name and id. This is sent between tags that are later (on javascript) separated for creating the dropdown list. It could be done in a much more elegant way
*/
function resource() {
    $value=clean_input($_GET['value']);
    $res=dbHelp::mysql_query2("select resource_id,resource_name from resource where resource_status<>2 and resource_type=" . $value);
    for ($i=0;$i<dbHelp::mysql_numrows2($res);$i++) {
        // mysql_data_seek($res,$i);
        $arr=dbHelp::mysql_fetch_array2($res);
        echo "<name>" . $arr['resource_name'];
        echo "<value>" . $arr['resource_id'];
    }
}

/**
   * @abstract returns user login for text input autofill.
   * @return ->name and id are returned separated by |
   * @todo It should be done using an object model. Waiting for version 2
*/

function user() {
    $value=clean_input($_GET['value']);
    $sql="select user_login,user_id from ".dbHelp::getSchemaName()."user where user_login like '" . $value . "%'";
    $res=dbHelp::mysql_query2($sql) or die ($sql);
    $arr=dbHelp::mysql_fetch_row2($res);
    echo $arr[0];
    echo "|" . $arr[1];

}

/**
   * calls recover password method for the selected user id
*/
function newpwd(){
    require_once("alertClass.php");
    $alert= new alert;
    $alert->recover($_GET['value']);

}

/**
   * text input autofill for administrating table. 
*/

function admin() {
    $value=clean_input($_GET['value']);
    $tag=clean_input($_GET['tag']);
    $table=clean_input($_GET['table']);
    $sql="show fields from $table";
    $res=dbHelp::mysql_query2($sql) or die ($sql);
    // mysql_data_seek($res,0);
    $field1=dbHelp::mysql_fetch_row2($res);
    // mysql_data_seek($res,1);
    $field2=dbHelp::mysql_fetch_row2($res);
    
    $sql="select " . $field2[0] . ",". $field1[0] . " from $table where lower(" . $field2[0] . ") like lower('" . $value . "%')";
    $res=dbHelp::mysql_query2($sql) or die ($sql);
    $arr=dbHelp::mysql_fetch_row2($res);
    echo $arr[0];
    echo "|" . $arr[1];
}
function DisplayUserInfo() {
    $value=clean_input($_GET['value']);
    // $sql="select concat(user_firstname, ' ', user_lastname) name,user_email,user_mobile,user_phone,user_phonext,department_name,institute_name,date_format(entry_datetime,'%H:%i') s,date_format(date_add(entry_datetime,interval resource_resolution*entry_slots minute),'%H:%i') e from ".dbHelp::getSchemaName()."user,entry,department,institute,resource where user_dep=department_id and department_inst=institute_id and entry_user=user_id and entry_resource=resource_id and entry_id=" . $value;
    // $sql="select user_firstname,user_lastname,user_email,user_mobile,user_phone,user_phonext,department_name,institute_name,date_format(entry_datetime,'%H:%i') s,date_format(date_add(entry_datetime,interval resource_resolution*entry_slots minute),'%H:%i') e from ".dbHelp::getSchemaName()."user,entry,department,institute,resource where user_dep=department_id and department_inst=institute_id and entry_user=user_id and entry_resource=resource_id and entry_id=" . $value;
	$sqlAux = "select resource_resolution,entry_slots from ".dbHelp::getSchemaName()."user,entry,department,institute,resource where user_dep=department_id and department_inst=institute_id and entry_user=user_id and entry_resource=resource_id and entry_id=" . $value;
    $res=dbHelp::mysql_query2($sqlAux) or die ($sqlAux);
    $arr=dbHelp::mysql_fetch_row2($res);
	
    $sql="select user_firstname,user_lastname,user_email,user_mobile,user_phone,user_phonext,department_name,institute_name,".dbHelp::getFromDate('entry_datetime','%H:%i')." as s,".dbHelp::getFromDate(dbHelp::date_add('entry_datetime',$arr[0]*$arr[1],'minute'),'%H:%i')." as e from ".dbHelp::getSchemaName()."user,entry,department,institute,resource where user_dep=department_id and department_inst=institute_id and entry_user=user_id and entry_resource=resource_id and entry_id=" . $value;
    $res=dbHelp::mysql_query2($sql) or die ($sql);
    $arr=dbHelp::mysql_fetch_row2($res);
    echo "<table>";
    // echo "<tr><td>Time: </td><td>" . $arr[7] ."-" .$arr[8] ."</td></tr>";
    // echo "<tr><td>Name: </td><td>" . $arr[0] . "</td></tr>";
    echo "<tr><td>Time: </td><td>" . $arr[8] ."-" .$arr[9] ."</td></tr>";
    echo "<tr><td>Name: </td><td>" . $arr[0] . " " . $arr[1] . "</td></tr>";
	// Only show this if a user is logged
	if(isset($_SESSION['user_id']) || $_SESSION['user_id']!= ''){
		// echo "<tr><td>Email: </td><td>" . $arr[1] . "</td></tr>";
		// echo "<tr><td>Mobile: </td><td>" . $arr[2] . "</td></tr>";
		// echo "<tr><td>Phone: </td><td>" . $arr[3] . "</td></tr>";
		// echo "<tr><td>Phone ext: </td><td>" . $arr[4] . "</td></tr>";
		// echo "<tr><td>Department: </td><td>" . $arr[5] . "</td></tr>";
		// echo "<tr><td>Institute: </td><td>" . $arr[6] . "</td></tr>";
		echo "<tr><td>Email: </td><td>" . $arr[2] . "</td></tr>";
		echo "<tr><td>Mobile: </td><td>" . $arr[3] . "</td></tr>";
		echo "<tr><td>Phone: </td><td>" . $arr[4] . "</td></tr>";
		echo "<tr><td>Phone ext: </td><td>" . $arr[5] . "</td></tr>";
		echo "<tr><td>Department: </td><td>" . $arr[6] . "</td></tr>";
		echo "<tr><td>Institute: </td><td>" . $arr[7] . "</td></tr>";
	}
    echo "</table>";
}

function DisplayEntryInfo() {
    $entry=clean_input($_GET['entry']);
    $sql ="select xfields_name,xfieldsval_value from xfieldsval,xfields where xfieldsval_field=xfields_id and  xfieldsval_entry=".$entry;
    $res=dbHelp::mysql_query2($sql) or die ($sql);
    
    for ($i=0;$i<dbHelp::mysql_numrows2($res);$i++) {
        // mysql_data_seek($res,$i);
        $arr=dbHelp::mysql_fetch_row2($res);
        echo "document.getElementById('" . $arr[0] . "').value='" . $arr[1] . "';";
    }
 
}

?>