<?php 
//include the information needed for the connection to MySQL data base server. 
// we store here username, database and password 
require_once ".htconnect.php"; 
require_once "session.php";
require_once "functions.php";
require_once "resClass.php";
$user_id = startSession();
// to the url parameter are added 4 parameters as described in colModel
// we should get these parameters to construct the needed query
// Since we specify in the options of the grid that we will use a GET method 
// we should use the appropriate command to obtain the parameters. 
// In our case this is $_GET. If we specify that we want to use post 
// we should use $_POST. Maybe the better way is to use $_REQUEST, which
// contain both the GET and POST variables. For more information refer to php documentation.
// Get the requested page. By default grid sets this to 1. 
$page = $_GET['page']; 
 
// get how many rows we want to have into the grid - rowNum parameter in the grid 
$limit = $_GET['rows']; 
// get index row - i.e. user click to sort. At first time sortname parameter -
// after that the index from colModel 
$sidx = $_GET['sidx']; 
 
// sorting order - at first time sortorder 
$sord = $_GET['sord']; 
 
//table to build query
if(isset($_GET['table'])) $table=$_GET['table'];
if(isset($_GET['type']))  $type=$_GET['type'];
if(isset($_GET['state'])) $state=$_GET['state'];

// if we not pass at first time index use the first column for the index or what you want
if(!$sidx) $sidx=1; 

//call database class and connect to database
$conn = new dbConnection();
$database = $conn->getDatabase();
$perm=new restrictClass();

//enter in this clause if type and state are sent through the URL
if(isset($type) and isset($state)){
	
	//get specific query from this type
	$sql = $conn->prepare("SELECT type_query FROM $database.type WHERE type_name='$type'");
	$sql->execute();
	$row = $sql->fetch();
	$query=$row[0];
	//initialize query
	$resquery=" AND basket_state IN (SELECT state_id FROM $database.state WHERE state_name='$state')";
	//handling basket restrictions
	if(isset($_GET['id']) and $_GET['id']!=""){
		$id=$_GET['id'];
		$resquery.=" AND request_basket=$id ";
	} else {
		//the basket still does not have an account
		$having=$perm->restrictAttribute($user_id, "basket");
		if($having!=""){
			$resquery.=" AND request_basket IN (SELECT basket_id FROM $database.basket WHERE $having)";
			$active_having = "request_basket IN (SELECT basket_id FROM $database.basket WHERE $having)";
		}
		if($state!="Active") { //account had already been chosen
			$having=$perm->restrictAttribute($user_id, "account");
			if($having!=""){
				$resquery.=" AND (request_basket IN (SELECT basket_id FROM $database.basket WHERE basket_account IN (SELECT account_id FROM account WHERE $having)) OR $active_having)";
			}
		}
	}
	$glue="UNION";
	$query=splitString($query, $glue, $resquery);
}

//enter in this clause if only state is sent through http
if(isset($state) and !isset($type)){
	//build specific query to display the basket list
	$resquery="";
	$active_having=$perm->restrictAttribute($user_id, "basket");
	if($active_having!="")$resquery=" AND $active_having";	
	if($state!="Active"){ //basket has already an account
		$having=$perm->restrictAttribute($user_id, "account");
		if($having!="")$resquery =" AND (basket_account IN (SELECT account_id FROM account WHERE $having) OR $active_having)";
	}
	$query = "SELECT basket_id, department_name, basket_sap, account_number, basket_submit_date, basket_order_date, basket_delivery_date, type_name, basket_obs FROM $database.basket, $database.type, $database.department, $database.account WHERE basket_type=type_id AND basket_user=department_id AND basket_account=account_id AND basket_state IN (SELECT state_id FROM $database.state WHERE state_name='$state') $resquery";	
}
// the actual query for the grid data 
$sql=$conn->prepare($query); 
//echo $sql->queryString;
$sql->execute();
//number of rows in the query
$count=$sql->rowCount();
// calculate the total pages for the query 
if( $count > 0 && $limit > 0) { 
              $total_pages = ceil($count/$limit); 
} else { 
              $total_pages = 0; 
} 
 
// if for some reasons the requested page is greater than the total 
// set the requested page to total page 
if ($page > $total_pages) $page=$total_pages;
 
// calculate the starting position of the rows 
$start = $limit*$page - $limit;
 
// if for some reasons start position is negative set it to 0 
// typical case is that the user type 0 for the requested page 
if($start <0) $start = 0; 
// constructing a JSON array (navigator)
$response->page = $page;
$response->total = $total_pages;
$response->records = $count;

// the actual query for the grid data 
$sql=$conn->prepare($query." ORDER BY $sidx $sord LIMIT $limit OFFSET $start"); 
//echo $sql->queryString;
$sql->execute();

for($i=0;$row=$sql->fetch();$i++){
	$response->rows[$i]["id"]=$row[0];
	$response->rows[$i]["cell"]=null;
	for($j=0;$j<$sql->columnCount();$j++){
		$arr[]=$row[$j];
	}
	$response->rows[$i]["cell"]=$arr;
	$arr=null;
}

//print_r(json_encode($response));exit();
// return the formated data
echo json_encode($response);
?>