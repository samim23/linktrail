#!/usr/local/bin/php -q
<?php

$mysql = mysql_pconnect('localhost', 'root','oelberg') or die("Could not connect to mysql");
mysql_select_db('linktrail') or die("Could not change the database");

$postgres = pg_connect("host=localhost user=postgres dbname=linktrail");

function begin_transaction(){
 global $postgres;
 pg_exec($postgres, "BEGIN WORK");
}

function end_transaction($success = true){
 global $postgres;
 if ($success) 
  pg_exec($postgres, "COMMIT WORK");
 else
  pg_exec($postgres, "ROLLBACK WORK");
}

function null_check(&$row, $fieldname){
  $row[$fieldname] = ($row[$fieldname] == "") ? "NULL" : "'".$row[$fieldname]."'";
}

function path2id($path){
 $query = "SELECT Node_ID FROM ltrDirectory WHERE Name = ".$path;
 $res = mysql_query($query);
 if (!$res) return "NULL";
 if (mysql_num_rows($res)) return "NULL";
 $row = mysql_fetch_array($res);
 if ($row['Node_ID'] == "") return "NULL";
 return $roe['Node_ID']; 
}

function do_users_table(){
 global $postgres;

 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrUserData") or die("Error while cleaning table");

 printf("\tReading from MySQL...\n");
 $query = "SELECT * FROM auth_user";
 $res   = mysql_query($query) or die("Could not query users\n\n");
 begin_transaction();
 while($row = mysql_fetch_array($query)){
  $query = sprintf("
   INSERT INTO
    auth_user
   (user_id, username, password, perms)
   VALUES
   ('%s', '%s', '%s', '%s')
  ",
  $row['user_id'], $row['username'], $row['password'], $row['perms']);
  $pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted User: %s", $row['username']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}

function do_users_data_table(){
 global $postgres;
 
 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrUserData") or die("Error while cleaning table");
 
 printf("\tReading from MySQL...\n");
 $query = "SELECT * FROM ltrUserData";
 $res   = mysql_query($query) or die("Could not query users data\n\n");
 begin_transaction();
 while($row = mysql_fetch_array($query)){
  foreach($row as $key => $value)
   $row[$key] = addslashes($value);

  null_check($row, 'Email');
  null_check($row, 'Homepage');
  null_check($row, 'Description');
  null_check($row, 'Gender');
  null_check($row, 'Age');
  null_check($row, 'Country');
  null_check($row, 'PhotoUrl');
  
  $query = sprintf("
   INSERT INTO
    ltrUserData
   (User_ID, FirstName, LastName, Email, Homepage, Description, Gender, Age, Country,
    PhotoUrl, ShowNobody, ShowFriend, ShowAnyone, PopupPos, NS6Sidebar, ResPerPage,
    HighlightSearch, IfaceLang, Langs, NotificationStyle, MypageAfterLogon, LastOnline,
    NewWindow, LastReadMessages, BounceFlag, LastSent)
   VALUES
    ('%s', '%s', '%s', %s, %s, %s, %s, %s, %s,
      %s, %d, %d, %d, '%s', '%s', %d,
      '%s', %d, %d, %d, '%s', '%s',
      '%s', '%s', 0, '%s')
   ",
  $row['User_ID'], $row['FirstName'], $row['LastName'], $row['Email'], $row['Homepage'], $row['Description'],
  $row['Gender'], $row['Age'], $row['Country'], $row['PhotoUrl'], $row['ShowNobody'], $row['ShowFriend'],
  $row['ShowAnyone'], $row['PopupPos'], $row['NS6Sidebar'], $row['ResPerPage'], $row['HighlightSearch'],
  $row['IfaceLang'], $row['Langs'], $row['NotificationStyle'], $row['MypageAfterLogon'], $row['LastOnline'],
  $row['NewWindow'], $row['LastReadMessages'], $row['BounceFlag'], $row['LastSent']
  );
  $pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted UID: %s", $row['User_ID']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}

function do_directory_table(){
 global $postgres;
 
 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrDirectory") or die("Error while cleaning table");
 
 printf("\tReading from MySQL... (and looking up nodes)\n");
 $query = "SELECT ltrDirectory.*, ltrDirectoryData.*, FROM_UNIXTIME(UNIX_TIMESTAMP(ChangeDate)) as CompDate FROM ltrDirectory LEFT JOIN ltrDirectoryData ON ltrDirectory.Node_ID = ltrDirectoryData.Node_ID";
 $res   = mysql_query($query) or die("Could not query users data\n\n");
 while($row = mysql_fetch_array($query)){
  foreach($row as $key => $value)
   $row[$key] = addslashes($value);
  if ($row['AddDate'] == "")
   $row['AddDate'] = $row['CompDate'];
  if ($row['AddDate'] == "0000-00-00 00:00:00")
   $row['AddDate'] = $row['CompDate'];
  null_check($row, 'IntNode');
  null_check($row, 'Owner');
  $nodes[] = $row;
  $nodes['LinkTo'] = path2id($row['LinkTo']);
 } 
 

  
  $query = sprintf("
   INSERT INTO
    ltrDirectory
   (Name, Node_ID, Parent, LinkTo, Level, Language, IntNode, Description, UserAccess,
    FriendAccess, ExtraLong, ChangeDate, Owner, AddDate)
   VALUES
    (
     '%s', %d, %d, %d, %d, %d, %s, '%s', %d,
     %d, %d, '%s', %s, '%s'
    )
   ",
  $row['Name'], $row['Node_ID'], $row['Parent'], $row['LinkTo'], $row['Level'], $row['Language'],
  $row['IntNode'], $row['Description'], $row['UserAccess'], $row['FriendAccess'], $row['ExtraLong'],
  $row['CompDate'], $row['Owner'], $row['AddDate']
  );
  begin_transaction();$pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted Node: %s", $row['Name']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}

function do_links_table(){
 global $postgres;
 
 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrLinks") or die("Error while cleaning table");
 
 printf("\tReading from MySQL...\n");
 $query = "SELECT *, FROM_UNIXTIME(UNIX_TIMESTAMP(ChangeDate)) as CompDate FROM ltrLinks";
 $res   = mysql_query($query) or die("Could not query links\n\n");
 begin_transaction();
 while($row = mysql_fetch_array($query)){
  foreach($row as $key => $value)
   $row[$key] = addslashes($value);

  null_check($row, 'Next');
  
  $query = sprintf("
   INSERT INTO
    ltrLinks
   (Trail, Name, Description, Url, Owner, ChangeDate, AddDate, Next)
   VALUES
    (%d, '%s', '%s', '%s', '%s', '%s', '%s', %s)
   ",
  $row['Trail'], $row['Name'], $row['Description'], $row['Url'], $row['Owner'], $row['ChangeDate'],
  $row['AddDate'], $row['Next']
  );
  $pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted Link: %s", $row['Name']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}


function do_messages_table(){
 global $postgres;
 
 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrMessages") or die("Error while cleaning table");
 
 printf("\tReading from MySQL...\n");
 $query = "SELECT * FROM ltrMessages";
 $res   = mysql_query($query) or die("Could not query users data\n\n");
 begin_transaction();
 while($row = mysql_fetch_array($query)){
  foreach($row as $key => $value)
   $row[$key] = addslashes($value);

  $row['Target'] = ($row['Target'] == "-1") ? "NULL" : "'".$row['Target']."'";
  
  $query = sprintf("
   INSERT INTO
    ltrMessages
   (Target, MessageType, Sender, Date, Done, Data, ReferenceCount)
   VALUES
    (%s, %d, '%s', '%s', '%s', '%s', %d)
   ",
  $row['Target'], $row['MessageType'], $row['Sender'], $row['Date'], $row['Done'], $row['Data'],
  $row['ReferenceCount']
  );
  $pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted Message: from=%s to=%s", $row['Sender'], $row['Target']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}


function do_slots_table(){
 global $postgres;
 
 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrSlots") or die("Error while cleaning table");
 
 printf("\tReading from MySQL...\n");
 $query = "SELECT * FROM ltrSlots";
 $res   = mysql_query($query) or die("Could not query slots data\n\n");
 begin_transaction();
 while($row = mysql_fetch_array($query)){
  
  foreach($row as $key => $value)
   $row[$key] = addslashes($value);

  null_check($row, 'Trail_2_ID');
  null_check($row, 'Trail_2_Text');
  null_check($row, 'Next');
  
  $query = sprintf("
   INSERT INTO
    ltrSlots
   (Node_ID, Trail_1_ID, Trail_1_Text, Trail_2_ID, Trail_2_Text, Description, Next, IsLive)
   VALUES
    (%d, %d, '%s', %s, %s, '%s', %s, '%s')
   ",
  $row['Node_ID'], $row['Trail_1_ID'], $row['Trail_1_Text'], $row['Trail_2_ID'], $row['Trail_2_Text'], 
  $row['Description'], $row['Next'], $row['IsLive']
  );
  $pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted Slot. Node: %d", $row['Node_ID']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}
 

function do_friends_table(){
 global $postgres;
 
 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrFriends") or die("Error while cleaning table");
 
 printf("\tReading from MySQL...\n");
 $query = "SELECT * FROM ltrFriends";
 $res   = mysql_query($query) or die("Could not query friends\n\n");
 begin_transaction();
 while($row = mysql_fetch_array($query)){
  foreach($row as $key => $value)
   $row[$key] = addslashes($value);

  $query = sprintf("
   INSERT INTO
    ltrFriends
   (Userid, IsFriendOf)
   VALUES
    ('%s', '%s')
   ",
  $row['Userid'], $row['IsFriendOf']);
  $pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted Friendship: (uid1=%s uid2=%s)", $row['Userid'], $row['IsFriendOf']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}


function do_subscriptions_table(){
 global $postgres;
 
 printf("\tCleaning postgres-table\n");
 pg_exec($postgres, "DELETE FROM ltrSubscriptions") or die("Error while cleaning table");
 
 printf("\tReading from MySQL...\n");
 $query = "SELECT ltrSubscriptions.Trail, auth_user.user_id FROM ltrSubscriptions, auth_user WHERE auth_user.username = ltrSubscriptions.Username";
 $res   = mysql_query($query) or die("Could not query subscriptions\n\n");
 begin_transaction();
 while($row = mysql_fetch_array($query)){
  foreach($row as $key => $value)
   $row[$key] = addslashes($value);

  $query = sprintf("
   INSERT INTO
    ltrSubscriptions
   (User_ID, Trail)
   VALUES
    ('%s', %d)
   ",
  $row['user_id'], $row['Trail']);
  $pg_res = pg_exec($postgres, $query)
  if (!$pg_res){
   end_transaction(false);
   die("SQL-Error! Query:\n\n$query\n\n");
  }else{
   printf("\tInserted Subscription: (Trail=%d uid=%s)", $row['Trail'], $row['user_id']);
  }
 }
 mysql_free_result($res);
 end_transaction();
}


printf("\nConverting users...");
do_users_table();
printf("\n\nConverting users data...");
do_users_data_table();
printf("\n\nConverting Directory...");
do_directory_table();
printf("\n\nConverting links...");
do_links_table();
printf("\n\nConverting messages...");
do_messages_table();
printf("\n\nConverting slots...");
do_slots_table();
printf("\n\nConverting friends...");
do_friends_table();
printf("\n\nConverting subscriptions...");
do_subscriptions_table()
printf("\n\nconversion done! Thanks for rewriting me!\n\n");
?>