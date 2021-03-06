<?php
//session_start();
	// This class was altered by Pedro Pires (The chosen two)
	require_once("commonCode.php");
	initSession();
?>

<?php
    /**
    * @author Nuno Moreno
    * @copyright 2009-2010 Nuno Moreno
    * @license http://www.gnu.org/copyleft/lesser.html Distributed under the Lesser General Public License (LGPL)
    * @version 1.0
    * @abstract: script for dealing with ajax weekview requests. It sets the entries into different states
    * 1-> regular, 2-> pre-reserve, 3->deleted, 4->Monitored
    */
    
// require_once(".htconnect.php");
// require_once("__dbHelp.php");
require_once("permClass.php");
require_once("alertClass.php");
require_once("functions.php");

$action=$_GET['action'];
//echo $action;
call_user_func($action);

function getUserId(){
	if(isset($_SESSION['user_id']) && $_SESSION['user_id']!='')
		return $_SESSION['user_id'];
	else 
		// return $_GET['user_id'];
		return clean_input($_GET['user_id']);
}

function getPass(){
	if(isset($_SESSION['user_pass']) && $_SESSION['user_pass']!='')
		return $_SESSION['user_pass'];
	else
		// return cryptPassword($_GET['user_passwd']);
		return cryptPassword(clean_input($_GET['user_passwd']));
}

//adding function. set the state to 1 or 2 depending on equipment state
function add(){
    $w=0;
    $update=clean_input($_GET['update']);
    if ($update>0) {update();exit;}
    $assistance=$_GET['assistance'];
    $code=clean_input($_GET['code']);
    $repeat=clean_input($_GET['repeat']);
    $enddate=clean_input($_GET['enddate']);
    $enddate=substr($enddate,0,4) . substr($enddate,5,2) . substr($enddate,8,2);
    $datetime=clean_input($_GET['datetime']);
    $min=substr($datetime,10,2);
    $hour=substr($datetime,8,2);
    $slots=clean_input($_GET['slots']);
    $assistance=($assistance=='true')?"1":"0";
    
    // $user_id=$_GET['user_id'];
    // $user_passwd=$_GET['user_passwd'];
    $user_id=getUserId();
    $user_passwd=getPass();
	// $user_passwd=cryptPassword($user_passwd);
    $resource=clean_input($_GET['resource']);
	
    //checking the permission 
    //$perm= new permClass;
    $perm= new permClass;

    if (!$perm->setPermission($user_id,$resource,$user_passwd)) {echo $perm->getWarning();exit;};
    if (!$perm->addRegular()) {echo $perm->getWarning();exit;};
    if (!$perm->addAhead($datetime)) {echo $perm->getWarning();exit;}
    if (!$perm->addBack($datetime)) {echo $perm->getWarning();exit;}
    $EntryStatus=$perm->getEntryStatus();
    if (!$perm->getEntryStatus()) {echo $perm->getWarning();exit;}
    
    //if there is no associated entries it creates a new set
    $sql="select repetition_id from repetition where repetition_code='".$code."'";
    $res=dbHelp::mysql_query2($sql) or die($sql) ;
    //if there is no related entry already it creates one
    if (dbHelp::mysql_numrows2($res)==0) {    
        $sql="insert into repetition(repetition_code) values(" . $code . ")";
        dbHelp::mysql_query2($sql) or die($sql);
    }

    //getting the entry code
    $sql="select repetition_id from repetition where repetition_code='". $code . "'";

    $res=dbHelp::mysql_query2($sql) or die($sql);
    $arrrep=dbHelp::mysql_fetch_row2($res);
	//201102251200
    $day=substr($datetime,6,2);
    $month=substr($datetime,4,2);
    $year=substr($datetime,0,4);
    $weekahead=$datetime;
    $notify=new alert($resource);   
    if ($repeat=='false') $enddate='999999999999';
    //building the repetition pattern
    while ((substr($weekahead,0,8)<=$enddate) && ($w<53)) {
        if (!$perm->addAhead($weekahead)) {echo $perm->getWarning();exit;}
        if (!$perm->checkOverlap($weekahead,$slots)) {echo $perm->getWarning();exit;}
        $sql="insert into entry(entry_user,entry_datetime,entry_slots,entry_assistance,entry_repeat,entry_status,entry_resource,entry_action,entry_comments) values(".$user_id.",".dbHelp::convertDateStringToTimeStamp($weekahead,'%Y%m%d%H%i')."," . $slots .",". $assistance ."," . $arrrep[0] .",". $EntryStatus . "," . $resource . ", '".date('Y-m-d H:i:s',time())."',NULL)";
        dbHelp::mysql_query2($sql) or die($sql);

        // $sql="SELECT LAST_INSERT_ID()";
        $sql="SELECT entry_id from entry where entry_user = ".$user_id." and entry_datetime = ".dbHelp::convertDateStringToTimeStamp($weekahead,'%Y%m%d%H%i')." and entry_repeat = " . $arrrep[0] ." and entry_resource = " . $resource;
        $res=dbHelp::mysql_query2($sql) or die($sql);
        $last=dbHelp::mysql_fetch_row2($res);
        $sql="select xfields_name,xfields_id from xfields,resxfields where resxfields_field=xfields_id and resxfields_resource=" . $resource;
        $res=dbHelp::mysql_query2($sql) or die($sql);
        $extra= array();
        for ($i=0;$i<dbHelp::mysql_numrows2($res);$i++) {
            // mysql_data_seek($res,$i);
            $arr=dbHelp::mysql_fetch_row2($res);
            $var=$arr[0];
            $val=clean_input($_GET[$var]);
            eval("\$$var='$val';");
            $sql="insert into xfieldsval(xfieldsval_entry,xfieldsval_field,xfieldsval_value) values(". $last[0] . "," . $arr[1] . ",'" . $val . "')";
            dbHelp::mysql_query2($sql) or die($sql);
            $extra[$arr[0]]=$val;
        }
        $notify->setSlots($slots);
        $notify->setEntry($last[0]);
        $notify->setUser($user_id);
        if ($assistance) {        
            $notify->toAdmin($weekahead,$extra,'assistance');
        } elseif ($perm->getResourceStatus()==4) {
            $notify->toAdmin($weekahead,$extra,'newentry');
        }
        if ($repeat=='false') $w=53;
        $w++;
        $weekaheadUTC=mktime(0,0,0,$month, $day+7*$w,$year);
        $weekahead=date("Ymd",$weekaheadUTC) . substr($datetime,8,4);
    }
    echo "entry(ies) added";
}
//changes the entry state to 3, ie, invisible
function del(){
    
    // $user_id=clean_input($_GET['user_id']);
    // $user_passwd=clean_input($_GET['user_passwd']);
	// $user_passwd=cryptPassword($user_passwd);
    $user_id=getUserId();
    $user_passwd=getPass();
	
    $resource=clean_input($_GET['resource']);
    $entry=clean_input($_GET['entry']);
    $seekentry='';
  
    // Gets the all the users, entry_ids and status of the given entry_id date, of a given resource
    $sql="SELECT entry_user,entry_id,entry_status FROM entry where entry_datetime=(select entry_datetime from entry where entry_id=".$entry.") and entry_resource=".$resource." AND entry_status IN ( 1, 2, 4 ) order by entry_id";
    $res=dbHelp::mysql_query2($sql) or die($sql);
    $found=false;
    $perm= new permClass;
    for ($i=0;$i<dbHelp::mysql_numrows2($res);$i++) {
        // mysql_data_seek($res,$i);
        $arr=dbHelp::mysql_fetch_row2($res);
		// Checks if the current user from the $res list is allowed to delete the current entry
        // if($perm->setPermission($arr[0],$resource,$user_passwd)){
		// Checks if the given user is allowed to delete the current entry
        if($perm->setPermission($arr[0],$resource,$user_passwd) && $arr[0]==$user_id){
            $found=true;
            $seekentry=$arr[1];
			// Not used anymore
            // $user_id=$arr[0];//it might be the admin logging in
            break;
        }
    }
    if (!$found) {echo $perm->getWarning();return;}
    
    $deleteall=$_GET['deleteall'];
    if ($entry!=$arr[1]) $deleteall=0; //delete from monitor does not allow delete all 
    

    // $extra =" and addtime(entry_datetime,'-" .  $perm->getResourceDelHour() . ":0:0') > now()";
    $extra =" and ".dbHelp::date_sub('entry_datetime',$perm->getResourceDelHour(),'hour')." > now()";
    if ($perm->addBack($arr[1])) $extra =""; //if you can delete back there is no time restriction
    
    $sql="select entry_repeat,".dbHelp::getFromDate('entry_datetime','%Y%m%d%H%i').",entry_status from entry where entry_id=". $entry;
    $res=dbHelp::mysql_query2($sql);
    $arr=dbHelp::mysql_fetch_row2($res);
    $status=$arr[2]; // to set the waitlist with the same status as previous one
    if ($deleteall==1){     
        $sql="update entry set entry_status=3 where entry_repeat=" . $arr[0] . $extra;
    } else {
        $sql="update entry set entry_status=3 where entry_id=" . $seekentry . $extra;
    }
    //echo $sql;
    $resPDO = dbHelp::mysql_query2($sql) or die ($sql);
    // if (mysql_affected_rows()==0) {
    if (dbHelp::mysql_numrows2($resPDO)==0) {
        echo "No permission to delete selected entry(ies)";
    } else {
		$notify=new alert($resource);
        $notify->setUser($user_id);
        $notify->setEntry($entry);
        echo "Entry(ies) deleted!";
        if ($entry==$seekentry) { //only eventually notify if not delete from monitor
		
            if (($perm->getResourceStatus()==4)) { // if there is a manager and user is the same as in the entry, ie, not admin              
                $notify->toAdmin($arr[1],'','delete');
            }
            // $sql="select @edt:=entry_datetime,@res:=entry_resource from entry where entry_id=". $entry;
            // $res=dbHelp::mysql_query2($sql) or die ($sql);
            
            $notify->toWaitList('delete'); // for waiting list. As to be send before update the entry to regular.    
            
            // $sql="update entry set entry_status=" . $status ." where entry_status=4 and entry_resource=@res and entry_datetime=@edt";
            $sql="update entry set entry_status=" . $status ." where entry_status=4 and (entry_resource, entry_datetime) in (select entry_resource,entry_datetime from entry where entry_id=". $entry.")";
            $res=dbHelp::mysql_query2($sql) or die ($sql);
            
        }
        
        //always notify if it was deleted from admin
        if($perm->getWasAdmin()) $notify->fromAdmin('delete');
  	// writeToFile("c:/a.txt", "bla");
    }
}


function update(){
    // $extra='';
    $datetime=clean_input($_GET['datetime']);
    $slots=clean_input($_GET['slots']);
    
    // $user_id=clean_input($_GET['user_id']);
    // $user_passwd=clean_input($_GET['user_passwd']);
	// $user_passwd=cryptPassword($user_passwd);
    $user_id=getUserId();
    $user_passwd=getPass();

    $resource=clean_input($_GET['resource']);
    $entry=clean_input($_GET['entry']);
    
    $perm= new permClass;
 
    if (!$perm->setPermission($user_id,$resource,$user_passwd)) {echo $perm->getWarning();return;}
    if (!$perm->addBack($datetime)) {echo $perm->getWarning();return;}

    // $extra =" and addtime(entry_datetime,'-" .  $perm->getResourceDelHour() . ":0:0') > now()";
    $extra =" and ".dbHelp::date_sub('entry_datetime',$perm->getResourceDelHour(),'hour')." > now()";
    if ($perm->addBack($arr[1])) $extra =""; //if you can delete back there is no time restriction
    //if (!$perm->addBack($datetime)) $extra =" and addtime(entry_datetime,'-" .  $perm->getResourceDelHour() . ":0:0') > now()";
    
    if (!$perm->addAhead($datetime)) {echo $perm->getWarning();return;}
    //checking datetime before update
    // $sql="select @edt:=entry_datetime,@res:=entry_resource,entry_user from entry where entry_id=". $entry;
    $sql="select entry_datetime,entry_resource,entry_user from entry where entry_id=". $entry;
    $resdt=dbHelp::mysql_query2($sql) or die($sql);
    $arrdt=dbHelp::mysql_fetch_row2($resdt);
    if ($user_id!=$arrdt[2]) {echo "Wrong User";exit;} // if update not from same user
	
    $sql="update entry set entry_user=".$user_id.", entry_datetime=".dbHelp::convertDateStringToTimeStamp($datetime,'%Y%m%d%H%i').",entry_slots=".$slots." where entry_id=". $entry;
    $resPDO = dbHelp::mysql_query2($sql . $extra) or die("User not updated!");
    
    // if (mysql_affected_rows()==0) {
    if (dbHelp::mysql_numrows2($resPDO)==0) {
        echo "User not updated. ";
    } else {
        //notification for waiting list
        // $sql="select entry_id, user_id from entry,".dbHelp::getSchemaName()."user where entry_user=user_id and entry_status=4 and entry_datetime=@edt and entry_resource=@res order by entry_id";
        $sql="select entry_id, user_id from entry,".dbHelp::getSchemaName()."user where entry_user=user_id and entry_status=4 and entry_datetime=".$arrdt[0]." and entry_resource=".$arrdt[1]." order by entry_id";
        $res=dbHelp::mysql_query2($sql);
        $arrStatus=dbHelp::mysql_fetch_row2($res);
        if (dbHelp::mysql_numrows2($res)>0) {
            $notify=new alert($resource);
            $notify->setUser($arrStatus[1]);
            $notify->setEntry($arrStatus[0]);
            $notify->toWaitList('update'); //only eventually notify if not delete from monitor
            
            $sql="delete from entry where entry_id=" . $arrStatus[0]; // deleting a monitoring entry
            dbHelp::mysql_query2($sql);
            //echo $sql
            
        }
    }
    
    $sql="select xfields_name,xfields_id from xfields,resxfields where resxfields_field=xfields_id and resxfields_resource=" . $resource;
    $res=dbHelp::mysql_query2($sql) or die($sql);
    for ($i=0;$i<dbHelp::mysql_numrows2($res);$i++) {
        // mysql_data_seek($res,$i);
        $arr=dbHelp::mysql_fetch_row2($res);
        $var=$arr[0];
        $val=$_GET[$var];
        $extra[$arr[0]]=$val;
        eval("\$$var='$val';");
        $sql="update xfieldsval set xfieldsval_value='$val' where xfieldsval_entry=$entry and xfieldsval_field=" . $arr[1];
        dbHelp::mysql_query2($sql) or die("Entry info not updated!");
        
    }
    $notify=new alert($resource);
    $notify->setUser($user_id);
    $notify->setSlots($slots);
    $notify->setEntry($entry);
    if ($perm->getResourceStatus()==4) {
        $notify->toAdmin($datetime,$extra,'update');
    }
    
    if ($perm->getWasAdmin()){
        $notify=new alert($resource);
        $notify->setEntry($entry);
        $notify->setUser($user_id);
        $notify->fromAdmin('update',$extra);
    }
    
    
    echo "Entry info updated!";
}
//set up one entry on top of another one and sets it up=4
function monitor(){

    // $user_id=clean_input($_GET['user_id']);
    // $user_passwd=clean_input($_GET['user_passwd']);
	// $user_passwd=cryptPassword($user_passwd);
    $user_id=getUserId();
    $user_passwd=getPass();

    $resource=clean_input($_GET['resource']);
    $entry=clean_input($_GET['entry']);
    $code=clean_input($_GET['code']);
    
    $perm= new permClass;
    if (!$perm->setPermission($user_id,$resource,$user_passwd)) {echo $perm->getWarning();return;}
    
    $sql="insert into repetition(repetition_code) values(" . $code . ")";
    dbHelp::mysql_query2($sql) or die($sql);
    
    $sql="select repetition_id from repetition where repetition_code='". $code . "'";
    $res=dbHelp::mysql_query2($sql) or die($sql);
    $arrrep=dbHelp::mysql_fetch_row2($res);

	require_once("commonCode.php");
    $sql="select entry.entry_datetime, resource.resource_resp from entry, resource where entry.entry_resource=resource.resource_id and entry_id=".$entry;
    $res=dbHelp::mysql_query2($sql) or die($sql);
	$currentDate = date('Y-m-d H:i:s',time());
    $arr=dbHelp::mysql_fetch_row2($res);
	// Only the "manager"/responsavel of a certain resource can monitor entries in the past
	if($currentDate > $arr[0] && $user_id != $arr[1]){
		echo "You cannot monitor entries in the past";
		exit;
	}

	
	// Block of code changed to stop users from getting in the waiting list more then once
    $sql="select * from entry where entry_user = ".$user_id." and entry_status != 3 and entry_datetime in (select entry_datetime from entry where entry_id=".$entry.")";
    $res=dbHelp::mysql_query2($sql) or die($sql);
    $arr=dbHelp::mysql_fetch_row2($res);
    // if ($arr[1]==$user_id) {echo "User already on the waiting list!";exit;};

	if(!empty($arr[0])){
		echo "User already on the waiting list!";
		exit;
	};
    $sql="select * from entry where entry_id=" . $entry;
    $res=dbHelp::mysql_query2($sql) or die($sql);
    $arr=dbHelp::mysql_fetch_row2($res);
    // end of block change
	
    $sql="insert into entry(entry_user,entry_datetime,entry_slots,entry_assistance,entry_repeat,entry_status,entry_resource,entry_action,entry_comments) values(" . $user_id . ",'" .$arr[2] . "',". $arr[3] . ",". $arr[4] . ",". $arrrep[0] . ",4," .$arr[7]. ",'".date('Y-m-d H:i:s',time())."',NULL)";  
    dbHelp::mysql_query2($sql) or die($sql);
    
    // $sql="SELECT LAST_INSERT_ID()";
	$sql="SELECT entry_id from entry where entry_user = ".$user_id." and entry_datetime = ".dbHelp::convertDateStringToTimeStamp($weekahead,'%Y%m%d%H%i')." and entry_repeat = " . $arrrep[0] ." and entry_resource = " . $resource;
    $res=dbHelp::mysql_query2($sql) or die($sql);
    $last=dbHelp::mysql_fetch_row2($res);
        
    $sql="select xfields_name,xfields_id from xfields,resxfields where resxfields_field=xfields_id and resxfields_resource=" . $resource;
        $res=dbHelp::mysql_query2($sql) or die($sql);
        for ($i=0;$i<dbHelp::mysql_numrows2($res);$i++) {
            // mysql_data_seek($res,$i);
            $arrx=dbHelp::mysql_fetch_row2($res);
            $sql="insert into xfieldsval(xfieldsval_entry,xfieldsval_field,xfieldsval_value) values(". $last[0] . "," . $arrx[1] . ",'Update info')";
            dbHelp::mysql_query2($sql) or die($sql);
        }
    echo "Entry monitored!";
    
}

//change the entry status from  2 to 1
function confirm(){

    // $user_id=clean_input($_GET['user_id']);
    // $user_passwd=clean_input($_GET['user_passwd']);
	// $user_passwd=cryptPassword($user_passwd);
    $user_id=getUserId();
    $user_passwd=getPass();

    $resource=clean_input($_GET['resource']);
    $entry=clean_input($_GET['entry']);

    $wasAdmin;
    $perm= new permClass;
    if (!$perm->setPermission($user_id,$resource,$user_passwd)) {
	$wasAdmin = $perm->getWasAdmin();
	echo $perm->getWarning();return;}

    
    if ($perm->getResourceStatus()==4 && $wasAdmin) {
    
        $notify=new alert($resource);
        $notify->setEntry($entry);
        $notify->fromAdmin('confirm');
        
    } elseif (!$perm->confirmEntry($entry)) {
        echo $perm->getWarning();
        exit;
    }
    echo $perm->getResourceStatus(),$perm->getWasAdmin();
    
    $sql="update entry set entry_status=1 where entry_id=" . $entry;
    $resPDO = dbHelp::mysql_query2($sql) or die($sql);
    // if (mysql_affected_rows()!=0)  echo $perm->getWarning();
    if (dbHelp::mysql_numrows2($resPDO)==0) echo $perm->getWarning();
    
    // $sql="select @dt:=entry_datetime from entry where entry_id=" . $entry;
    // dbHelp::mysql_query2($sql) or die($sql);
    // $sql="delete from entry where entry_datetime=@dt and entry_status in (1,2,4) and entry_id<>". $entry . " and entry_resource=" . $resource;
    $sql="delete from entry where entry_datetime in (select entry_datetime from entry where entry_id=".$entry.") and entry_status in (1,2,4) and entry_id<>". $entry . " and entry_resource=" . $resource;
    dbHelp::mysql_query2($sql) or die($sql);
    
}

function addcomments(){
    
    $resource=clean_input($_GET['resource']);
    $entry=clean_input($_GET['entry']);
    $comments=clean_input($_GET['comments']);
    
	// $user=clean_input($_GET['user_id']);
    $user_id=getUserId();

    $notify=new alert($resource);
    $notify->setEntry($entry);
    $notify->setUser($user);
    
    $sql="update entry set entry_comments='" . $comments . " 'where entry_id=" . $entry;
    if ($comments!='') $notify->toAdmin(date("YmdHi"),'','comment',$comments); 
    dbHelp::mysql_query2($sql) or die($sql);
    echo "Comment added";
}
?>
