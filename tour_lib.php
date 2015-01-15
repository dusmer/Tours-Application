<?


############################################################################
#       tour_lib.php
#
#   Version: 1.00
#
#
############################################################################



#######################################################################


include ('Mail.php');
require_once('DB.php');


  $DB = "ores_prod";
  $HOST = "csmgenr2.uvic.ca";
  $USER = "ores";
  $PWD = "ab49x2";

  $sysadmin = "dustinm@uvic.ca";
  $sr_email = 'Tours <tours@uvic.ca>';
  $emailfrom = "Tours <tours@uvic.ca>";


  #############################################################

  function LOG_SQL_ERROR ($sql,$area){

  global $sr_email,$sysadmin;

    $subject = "SQL Error for area $area";
    $body = "$sql";

    EMAIL($sr_email,$sysadmin,$subject,$body);
  }
#############################################################

function LOG_HTTP () {

#write to file and to database

 $http_logfile = "tours_log.csv";
 $ref = $_SERVER['HTTP_REFERER'];
 $addr = $_SERVER['REMOTE_ADDR'];
 $agent = $_SERVER['HTTP_USER_AGENT'];


 //test to see if file exists. If not create it with header else simply append

  if (file_exists($http_logfile)) {
   //print "yes the global file $http_logfile exists.";
    $FH = fopen($http_logfile,"a");
  }
  else {
   //create the file
    $FH = fopen($http_logfile,"w");
    fwrite($FH,"DATE,HTTP_REFERER,REMOTE_ADDR,HTTP_USER_AGENT\n");
  }

  $dt = date("D dS M,Y h:i a");
    //print "<BR><B>$ref,$addr,$agent</B>";


  fwrite($FH,"'$dt','$ref','$addr','$agent'\n");

  fclose($FH);

}//end function log_http

#####################################################################
function CONNECT_DB ($host,$user,$password,$database){
  //CONNECT to DATABASE

$dbh = DB::connect("mysql://$user:$password@$host/$database");
if(DB::iserror($dbh)) {
        #print "<br/><strong>***Connection ERROR: </strong> ";
        #print($db->getMessage());
}
else{
        return $dbh;
}

}//end connect_db

#########################################################################

function EMAIL ($from,$to,$subject,$msg) {

/*** old code, did not send in html
$recipients = $to;

$headers['From']    = $from;
$headers['To']      = $to;
$headers['Subject'] = $subject;
$headers['HTML'] = 'MIME-Version: 1.0' . "\r\n";
$headers['HTML'] .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

$body = $msg;

$params['sendmail_path'] = '/usr/lib/sendmail';

// Create the mail object using the Mail::factory method
$mail_object =& Mail::factory('sendmail', $params);

$mail_object->send($recipients, $headers, $body);
***/
$headers = "From: $from" . "\r\n" . "Reply-To: $from" . "\r\n";
$headers .= 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";			
$message = $msg;			
$newmsg = mail($to, $subject, $message, $headers);

}#end function email


######################################################################

/***
 * Add a PST Request to a tour
 ***/

function ADD_TO_TOUR($dbh, $POST){

	$Request_IDX = $_POST['Request_IDX'];
	$Tours_IDX = $_POST['selectedTour'];
	$Processed = $_POST['Processed'];


	if ($Processed==Y){
		$msg = "Error: Request already processed";
		return $msg;
	}
	else {
		$query = "UPDATE tours_PSTRequests SET Assigned_Tour_IDX = '$Tours_IDX', Processed = 'Y' WHERE Request_IDX = '$Request_IDX'";
		$q = $dbh->query($query);
		if(DB::isError($q)){
			LOG_SQL_ERROR($query, 'ADD_TO_TOUR');
			return "Error Updating Database";
		}
		$msg = $Tours_IDX;

		$Tour_Date = GET_TOUR_DATE($dbh,$Tours_IDX);

                $query = "UPDATE tours_PSTRequests SET Request_Date = '$Tour_Date' WHERE Request_IDX = '$Request_IDX'";
                $q = $dbh->query($query);
                if(DB::isError($q)){
                        LOG_SQL_ERROR($query, 'ADD_TO_TOUR');
                        return "Error Updating Request Date";
                }

		return $msg;
	}
	
}#end function ADD_TO_TOUR
########################################################################

/***
 * Add a Group Request to a tour
 ***/

function ADD_TO_TOUR_GROUP($dbh, $POST, $GroupName){

        $Group_IDX = $_POST['Group_IDX'];
        $Tours_IDX = $_POST['selectedTour'];
        $Processed = $_POST['Processed'];
        if ($Processed==Y){
                $msg = "Error: Request already processed";
                return $msg;
        }
        else {
                $query = "UPDATE tours_GroupRequests SET Assigned_Tour_IDX = '$Tours_IDX', Processed = 'Y' WHERE Group_IDX = '$Group_IDX'";
                $q = $dbh->query($query);
                if(DB::isError($q)){
                        LOG_SQL_ERROR($query, 'ADD_TO_TOUR_GROUP');
                        return "Error Updating Database";
                }
		$Tour_Date = GET_TOUR_DATE($dbh,$Tours_IDX);
		$query = "UPDATE tours_GroupRequests SET Tour_Date = '$Tour_Date' WHERE Group_IDX = '$Group_IDX'";
                $q = $dbh->query($query);
                if(DB::isError($q)){
                        LOG_SQL_ERROR($query, 'ADD_TO_TOUR_GROUP');
                        return "Error Updating Request Date";
                }
		
		$query = "SELECT TourName FROM tours_Tours WHERE Tours_IDX = '$Tours_IDX'";
		$tn = $dbh->getOne($query);
		if (!$tn){
			$query = "UPDATE tours_Tours SET TourName = CONCAT(TourName,'$GroupName') WHERE Tours_IDX = '$Tours_IDX'";
			$q = $dbh->query($query);
                	if(DB::isError($q)){
                	        LOG_SQL_ERROR($query, 'ADD_TO_TOUR_GROUP');
                	        return "Error Updating Tour Info";
               		}
		}
		else{
                	$query = "UPDATE tours_Tours SET TourName = CONCAT(TourName,' ','$GroupName') WHERE Tours_IDX = '$Tours_IDX'";
                	$q = $dbh->query($query);
                	if(DB::isError($q)){
                	        LOG_SQL_ERROR($query, 'ADD_TO_TOUR_GROUP');
                	        return "Error Updating Tour Info";
                	}

		}

                $msg = $Tours_IDX;
                return $msg;
        }

}
######################################################################

/****
 * Add multiple PST requests to tour
 * currently not in use
 ****/

function MULT_ADD_TO_TOUR($dbh, $POST){

  global $sr_email,$sysadmin;


        $Tours_IDX = $_POST['selectedTour'];
        $Processed = $_POST['Processed'];

	foreach ($_POST['multIDX'] as $value){
        	if ($Processed==Y){
        	        $msg = "\n Request already processed \n";
        	   	return $msg;
        	}
        	else {
                	$query = "UPDATE tours_PSTRequests SET Assigned_Tour_IDX = '$Tours_IDX', Processed = 'Y' WHERE Request_IDX = '$value'";
                	$q = $dbh->query($query);
                	if(DB::isError($q)){
                	        LOG_SQL_ERROR($query, 'MULT_ADD_TO_TOUR');
                	        return "Error Updating Database";
                	}






/*****
 *
 * Send e-mail confirmation to each client
 * 
 *****/


                	$query = "SELECT First_Name, Last_Name, Email FROM tours_PSTRequests WHERE Request_IDX = '$value'";
                	$client = $dbh->getRow($query);
			if (DB::isError($role)){
   	        	     LOG_SQL_ERROR($query, 'RETURN_STAFF_ROLE');
                	     return "Error Getting Staff Role"; 
                	}
                
			$FirstName = $client[0];
                	$LastName = $client[1];
                	$Email = $client[2];


	
                	$DateQuery = "SELECT Date FROM tours_Tours WHERE Tours_IDX = '$Tours_IDX'";
                	$date = $dbh->getRow($DateQuery);
                	if (DB::isError($date)){
                		LOG_SQL_ERROR($DateQuery, 'AddToTour');
                	        return "Error";
                	}
                	$TourDate = $date[0];
                	$date = DATE_CONVERSION($TourDate);

                        $emailMsg = "Hello,\n \n This is a confirmation e-mail sent by UVic tours, confirming $FirstName $LastName, is scheduled for a tour on $date. \n\n If you have any questions please e-mail $sr_email.  Thank you, we look forward to seeing you.\n\n Sincerely \n\n Tours Guide";
                        Email($sr_email, $Email, "Confirming Tour", "$emailMsg");




   		}
	$msg = "<font size=\"+1\">Requests Succesfully Added To Tour</font>";
	}
	return $msg;

}#end function MULT_ADD_TO_TOUR
######################################################################

/****
 * Add multiple group requests to a tour
 * Currently not in use
 ****/

function MULT_ADDGROUP_TO_TOUR($dbh, $POST){

  global $sr_email,$sysadmin;


        $Tours_IDX = $_POST['selectedTour'];
        $Processed = $_POST['Processed'];

        foreach ($_POST['multIDX'] as $value){
                if ($Processed==Y){
                        $msg = "\n Request already processed \n";
                        return $msg;
                }
                else {
                        $query = "UPDATE tours_GroupRequests SET Assigned_Tour_IDX = '$Tours_IDX', Processed = 'Y' WHERE Group_IDX = '$value'";
                        $q = $dbh->query($query);
                        if(DB::isError($q)){
                                LOG_SQL_ERROR($query, 'MULT_ADDGROUP_TO_TOUR');
                                return "Error Updating Database";
                        }

/*****
 *
 * Send e-mail confirmation to each client
 *
 *****/

                        $query = "SELECT FirstNameLeader, LastNameLeader, GroupEmail, GroupName FROM tours_GroupRequests WHERE Group_IDX = '$value'";
                        $client = $dbh->getRow($query);
                        if (DB::isError($role)){
                             LOG_SQL_ERROR($query, 'RETURN_EMAIL_INFO');
                             return "Error Getting Email Info";
                        }

                        $FirstName = $client[0];
                        $LastName = $client[1];
                        $Email = $client[2];
			$GroupName = $client[3];



                        $DateQuery = "SELECT Date FROM tours_Tours WHERE Tours_IDX = '$Tours_IDX'";
                        $date = $dbh->getRow($DateQuery);
                        if (DB::isError($date)){
                                LOG_SQL_ERROR($DateQuery, 'AddToTour');
                                return "Error";
                        }
                        $TourDate = $date[0];
                        $date = DATE_CONVERSION($TourDate);

                        $emailMsg = "Hello,\n \n This is a confirmation e-mail sent by UVic tours, confirming $FirstName $LastName, has booked a school group tour for $GroupName on $date. \n\n If you have any questions please e-mail $sr_email.  Thank you, we look forward to seeing you.\n\n Sincerely \n\n Tours Guide";
                        Email($sr_email, $Email, "Confirming Tour", "$emailMsg");

                }
        $msg = "<font size=\"+1\">School Group Requests Succesfully Added To Tour</font>";
        }
        return $msg;

}#end function MULT_ADDGROUP_TO_TOUR

########################################################################

/****
 * Set the PST request tour assignment to 0
 ****/

function REASSIGN($dbh, $reassignIDX){
	$query = "UPDATE tours_PSTRequests SET Assigned_Tour_IDX = '0', Processed = 'N', Attended = '1' WHERE Request_IDX = '$reassignIDX'";
	$q = $dbh->query($query);
	if(DB::isError($q)){
		LOG_SQL_ERROR($qyery, 'REASSIGN');
		return "Error Reassigning Request";
	}
	$msg = "Request Unassigned";
	return $msg;

} #end function REASSIGN

#########################################################################

/****
 * Set the group request tour assignment to 0
 ****/

function REASSIGN_GROUP($dbh, $reassignIDX){
        $query = "UPDATE tours_GroupRequests SET Assigned_Tour_IDX = '0', Processed = 'N' WHERE Group_IDX = '$reassignIDX'";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($qyery, 'REASSIGN_GROUP');
                return "Error Reassigning Group Request";
        }
        $msg = "Group Request Unassigned";
        return $msg;

} #end function REASSIGN

#########################################################################

/****
 * Inserts a request from a PST client into the system
 ****/

function INSERT_REQUEST($dbh, $POST){
	$First_Name = $_POST['firstName'];
	$Last_Name = $_POST['lastName'];
	$Tour_Date = $_POST['tourDate'];
	$Email = $_POST['email'];
	$conEmail = $_POST['conEmail'];
	$Phone = $_POST['phone'];
	$City = $_POST['city'];
	$ProvState = $_POST['stateProv'];
	$Country = $_POST['Country'];
	$Stu_Num = $_POST['stuNum'];
	$Program = $_POST['program'];
	$Role = $_POST['role'];
	$School_Level = $_POST['schoolLevel'];
	$Forwarded = $_POST['forwarded'];
	$Other = $_POST['other'];
	$Accom = $_POST['accom'];
	$Comments = $_POST['comments'];
	$NumOfAttendees = $_POST['NumOfAttendees'];
	$NumOfAdults = $_POST['NumOfAdults'];
	$NumOfYouths = $_POST['NumOfYouths'];
	$prospective1 = $_POST['prospective1'];
	$prospective2 = $_POST['prospective2'];
	$prospective3 = $_POST['prospective3'];

        if ($Email != $conEmail){
                $msg = "Error, E-mail Addresses Do Not Match $Email != $conMail";
		return $msg; 
       }
	if (!$First_Name || !$Last_Name || !$Tour_Date || !$Email || !$Program || !$Phone || !$City || !$ProvState){
		$msg = "Error: Request NOT submitted. Please fill out all sections marked with an asterisk(*)";
		return $msg;
	}


	if (!$Other){
		$query = "INSERT into tours_PSTRequests (Request_Date,First_Name,Last_Name,Program,Role,School_Level,Phone,Stu_Num,City,ProvState,Email,Forwarded,Accom,Comments,NumOfAttendees,NumOfAdults,NumOfYouths,Country) VALUES('$Tour_Date', '$First_Name', '$Last_Name', '$Program', '$Role', '$School_Level', '$Phone', '$Stu_Num', '$City', '$ProvState', '$Email', '$Forwarded', \"$Accom\", \"$Comments\", '$NumOfAttendees', '$NumOfAdults', '$NumOfYouths', '$Country')";
		$q = $dbh->query($query);
	}
	else{
                $query = "INSERT into tours_PSTRequests (Request_Date,First_Name,Last_Name,Program,Role,School_Level,Phone,Stu_Num,City,ProvState,Email,Forwarded,Accom,Comments,NumOfAttendees,NumOfAdults,NumOfYouths,Country) VALUES('$Tour_Date', '$First_Name', '$Last_Name', '$Program', '$Role', '$School_Level', '$Phone', '$Stu_Num', '$City', '$ProvState', '$Email', '$Other', \"$Accom\", \"$Comments\", '$NumOfAttendees', '$NumOfAdults', '$NumOfYouths', '$Country')";
                $q = $dbh->query($query);
	}

        $insertIDX = mysql_insert_id();

        if (file_exists("csv/ProStuTourRequests.csv")==0){
                $fp = @fopen("csv/ProStuTourRequests.csv", "w");
                $headings = "First Name,Last Name,Request Date,Email,Phone,City,ProvState,Country,Student Number,Interested Program,Role,Level In School,How Did You Hear About Tours,Other how did you hear abour tours,Special Accomodations, Comments, Number of Attendees, Number of Adults, Number of Youths, Prospective Student 1, Prospective Student 2, Prosepective Student 3 \n\r"; 
                fwrite($fp, $headings);
                $results = "\"$First_Name\",\"$Last_Name\",\"$Tour_Date\",\"$Email\",\"$Phone\",\"$City\",\"$ProvState\",\"$Country\",\"$Stu_Num\",\"$Program\",\"$Role\",\"$School_Level\",\"$Forwarded\",\"$Other\",\"$Accom\",\"$Comments\",\"$NumOfAttendees\",\"$NumOfAdults\",\"$NumOfYouths\",\"$prospective1\",\"$prospective2\",\"$prospective3\",  \r\n";
                fwrite($fp, $results);
        }


        else{
                $fp = @fopen("csv/ProStuTourRequests.csv", "a");               
                $results = "\"$First_Name\",\"$Last_Name\",\"$Tour_Date\",\"$Email\",\"$Phone\",\"$City\",\"$ProvState\",\"$Country\",\"$Stu_Num\",\"$Program\",\"$Role\",\"$School_Level\",\"$Forwarded\",\"$Other\",\"$Accom\",\"$Comments\",\"$NumOfAttendees\",\"$NumOfAdults\",\"$NumOfYouths\",\"$prospective1\",\"$prospective2\",\"$prospective3\",  \r\n";
                fwrite($fp, $results);

        # printing the contents of the results
        }
        fclose($fp);

        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'INSERT INVENTORY');
                $msg = "SQL ERROR\n";
                return $msg;
        }

	$queryContact = "INSERT into tours_Clients (Name,Request_IDX,Contact) VALUES('$First_Name $Last_Name','$insertIDX','1')";
	$q = $dbh->query($queryContact);
	
	if ($prospective1){
		$query2 = "INSERT into tours_Clients (Name,Request_IDX,Prospective) VALUES('$prospective1', '$insertIDX', '1')";
		$q = $dbh->query($query2);
	}
        if ($prospective2){
                $query2 = "INSERT into tours_Clients (Name,Request_IDX,Prospective) VALUES('$prospective2', '$insertIDX', '1')";
                $q = $dbh->query($query2);
        }
        if ($prospective3){
                $query2 = "INSERT into tours_Clients (Name,Request_IDX,Prospective) VALUES('$prospective3', '$insertIDX', '1')";
                $q = $dbh->query($query2);
        }

        if(DB::isError($q)){
                LOG_SQL_ERROR($query2,'INSERT INVENTORY');
                $msg = "SQL ERROR\n";
                return $msg;
        }


	$msg = "<br /><br />Your tour request has been added to the system.  A confirmation e-mail will be sent to you when the request is processed.";

	return $msg;

}#end function INSERT_REQUEST

######################################################################################################

/****
 * Update the attendance status of the PST request
 ****/

function UPDATE_ATTENDENCE($dbh,$attendedIDX,$attended){
	$query = "UPDATE tours_PSTRequests SET Attended=\"$attended\" WHERE Request_IDX = \"$attendedIDX\"";
	$q = $dbh->query($query);
	if(DB::isError($q)){
		LOG_SQL_ERROR($query,'UPDATE_ATTENDENCE');
		return "Error Updating Attendence Status";
	}
	else{
		return "Attendence Updated Successfully";
	}
}#end function UPDATE_ATTENDENCE

#######################################################################################################	

/****
 * Create a tour within the system
 ****/

function CREATE_TOUR($dbh, $POST){
	$date = $_POST['input9'];
	$type = $_POST['tourType'];

	$query = "INSERT into tours_Tours (Date,Type) VALUES('$date', '$type')";
        $q = $dbh->query($query);

	if(DB::isError($q)){
		LOG_SQL_ERROR($query, 'CREATE_TOUR');
		return "Error Adding Tour";
	}

	else{
		$msg = "Tour Successfully Created ";
		$query = "SELECT LAST_INSERT_ID()";
		$q = $dbh->getRow($query);
		$tourIDX = $q[0];
		$guide = $_POST['guide'];
		$officer = $_POST['officer'];
		$recruiter = $_POST['recruiter'];
		$studentpanel = $_POST['studentpanel'];

		foreach ($guide as $value){
				if ($value != 0){
				$Assign = "INSERT into tours_Tour_Assign (Tour_IDX,Staff_IDX) VALUES('$tourIDX', '$value')";
        			$q1 = $dbh->query($Assign);

				if(DB::isError($q1)){
                			LOG_SQL_ERROR($query, 'CREATE_TOUR');
                			return "Error Assigning Staff";
				}
				$name = "SELECT name From tours_Staff WHERE Staff_IDX = '$value'";
				$q2 = $dbh->getRow($name);
				$name = $q2[0];
			
			}
        	}
                foreach ($officer as $value){
                        if ($value != 0){
	
	                        $Assign = "INSERT into tours_Tour_Assign (Tour_IDX,Staff_IDX) VALUES('$tourIDX', '$value')";
	                        $q1 = $dbh->query($Assign);
	
                        	if(DB::isError($q1)){
                        	        LOG_SQL_ERROR($query, 'CREATE_TOUR');
                        	        return "Error Assigning Staff";
                        	}
                        	$name = "SELECT name From tours_Staff WHERE Staff_IDX = '$value'";
                        	$q2 = $dbh->getRow($name);
                        	$name = $q2[0];
                       
			}
                }
                foreach ($recruiter as $value){
                        if ($value != 0){
                        	$Assign = "INSERT into tours_Tour_Assign (Tour_IDX,Staff_IDX) VALUES('$tourIDX', '$value')";
                        	$q1 = $dbh->query($Assign);

                        	if(DB::isError($q1)){
                        	        LOG_SQL_ERROR($query, 'CREATE_TOUR');
                        	        return "Error Assigning Staff";
                        	}
                        	$name = "SELECT name From tours_Staff WHERE Staff_IDX = '$value'";
                        	$q2 = $dbh->getRow($name);
                        	$name = $q2[0];
                      
			}
                }
                foreach ($studentpanel as $value){
                        if ($value != 0){
	                        $Assign = "INSERT into tours_Tour_Assign (Tour_IDX,Staff_IDX) VALUES('$tourIDX', '$value')";
        	                $q1 = $dbh->query($Assign);

                	        if(DB::isError($q1)){
                        	        LOG_SQL_ERROR($query, 'CREATE_TOUR');
                                	return "Error Assigning Staff";
                        	}
                        	$name = "SELECT name From tours_Staff WHERE Staff_IDX = '$value'";
                        	$q2 = $dbh->getRow($name);
                        	$name = $q2[0];
                     

                	}
		}

		return $msg;

	}
}
#########################################################################################################

/****
 * Find the role name of the requested staff member
 ****/

function RETURN_STAFF_ROLE($dbh, $RoleIDX){
	$query = "SELECT Role FROM tours_Staff_Role WHERE SR_IDX = '$RoleIDX'";
	$role = $dbh->getRow($query);
	if (DB::isError($role)){
		LOG_SQL_ERROR($query, 'RETURN_STAFF_ROLE');
		return "Error Getting Staff Role";
	}
	$rolename = $role[0];
	return $rolename;
}//end of RETURN_STAFF_ROLE
##########################################################################################################

/****
 * Find the name of the requested staff member
 ****/

function RETURN_STAFF_NAME($dbh, $StaffIDX){
        $query = "SELECT FirstName, LastName FROM tours_Staff WHERE Staff_IDX = '$StaffIDX'";
        $name = $dbh->getRow($query);
        if (DB::isError($name)){
                LOG_SQL_ERROR($query, 'RETURN_STAFF_ROLE');
                return "Error Getting Staff Role";
        }
	$first = $name[0];
	$last = $name[1];
        $staffname = "$first $last";
        return $staffname;
}//end of RETURN_STAFF_NAME
##########################################################################################################

/****
 * Find the IDX of the role assigned to a staff member
 ****/

function RETURN_STAFF_ROLENUM($dbh, $StaffIDX){
        $query = "SELECT Role FROM tours_Staff WHERE Staff_IDX = '$StaffIDX'";
        $rolenum = $dbh->getRow($query);
        if (DB::isError($rolenum)){
                LOG_SQL_ERROR($query, 'RETURN_STAFF_ROLENUM');
                return "Error Getting Staff Role Number";
        }
        $role = $rolenum[0];
        return $role;
}//end of RETURN_STAFF_ROLENUM
##########################################################################################################

/****
 * Assign a staff member to a tour
 ****/

function ASSIGN_STAFF($dbh, $StaffIDXS, $TourIDX){
	foreach ($StaffIDXS as $StaffIDX){
		$duplicate = "SELECT * from tours_Tour_Assign WHERE Staff_IDX = '$StaffIDX' AND Tour_IDX = '$TourIDX'";
		$d = $dbh->getRow($duplicate);

		if (!$d){
        		$query = "INSERT into tours_Tour_Assign (Tour_IDX,Staff_IDX) VALUES('$TourIDX', '$StaffIDX')";
        		$q = $dbh->query($query);

        		if(DB::isError($q)){
        		        LOG_SQL_ERROR($query, 'ASSIGN_STAFF');
        		        return "Error Assigning Staff";
        		}
		}
	}
	return "Success";


}//end of ASSIGN_STAFF
##########################################################################################################

/****
 * Remove the staff members tour assignment from the system
 ****/

function REASSIGN_STAFF($dbh, $TAIDXs){
  foreach ($TAIDXs as $TAIDX){
  	$query = "DELETE from tours_Tour_Assign WHERE TA_IDX = $TAIDX LIMIT 1";
  	$q = $dbh->query($query);
  	#print $query;
  	if(DB::isError($q)){
  	      print($results->getMessage());
  	}
  }
}//End of REASSIGN_STAFF
###########################################################################################################

/****
 * Create a new staff member in the system
 ****/

function ADD_STAFF($dbh, $POST){
	$firstname = $_POST['staffFirstName'];
	$lastname = $_POST['staffLastName'];
	$email = $_POST['staffEmail'];
	$role = $_POST['staffRole'];

        $firstname = CLEAN_ANSWER($firstname);
        $lastname = CLEAN_ANSWER($lastname);
        $email = CLEAN_ANSWER($email);

        $emailError = eregi('^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z.]{2,5}$', $email);

        if (!$emailError){
                return "Error: Incorrect email address format.";
        }


	$roleName = RETURN_STAFF_ROLE($dbh, $role);

	$queryDuplicate = "SELECT Email FROM tours_Staff WHERE 1";
	$results = $dbh->getAll($queryDuplicate);
	foreach ($results as $result){
		$EmailCheck = $result[0];
		if ($EmailCheck == $email){
			return "Error: Staff member already registered with <strong>$email</strong>.  Staff member not added to system.";
		}
	}

        $query = "INSERT into tours_Staff (FirstName,LastName,Email,Role) VALUES('$firstname', '$lastname', '$email', '$role')";
        $q = $dbh->query($query);

        if(DB::isError($q)){
                LOG_SQL_ERROR($query, 'ADD_STAFF');
                return "Error Adding Staff";
        }
	$msg = "Staff Name: <strong>$firstname $lastname</strong><BR>Staff Email: <strong>$email</strong><br>Role: <strong>$roleName</strong><br><br>Added to System";
	return $msg;
}//end of ADD_STAFF
###########################################################################################################

/****
 * Check the users login information
 ****/

function LOGIN ($dbh,$uid,$pwd) {

  $query = "SELECT Users_IDX, Name FROM tours_Users WHERE UserName = '$uid' and Pwd = '$pwd' and Active = 'Y'";
  list($IDX,$Name) = $dbh->getRow($query);

  if(DB::isError($IDX)){
        LOG_SQL_ERROR($query,'LOGIN');
  }


  if (!$IDX){
   return false;
  }
  else{
   return true;
 }

}#end function login


#############################################################################

/****
 * Set the active status of a tour to N
 ****/

function DEACTIVATE_TOUR ($dbh, $removeIDX){
        $query = "UPDATE tours_Tours SET Active='N' WHERE Tours_IDX = \"$removeIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Deactivate_Tour');
                return "Error Deactivating Tour";
        }
        else{
                return "Tour Removed From Active List";
	}


}

#############################################################################

/****
 * Convert a date from MySQL (2008-09-26) form, to Friday, September 26, 2008 form
 ****/

function DATE_CONVERSION($date){
        $datevalues = explode("-", $date);
        $year = $datevalues[0];
        $month = $datevalues[1];
        $day = $datevalues[2];

        $newDate = date("l, F j, Y", mktime(0, 0, 0, $month, $day, $year));


	return $newDate;
}
#############################################################################

/****
 * Add a new user to the system
 ****/

function ADD_USER($dbh, $POST){
	$uid = $_POST['uidUser'];
	$pwd = $_POST['pwdUser'];	
        $name = $_POST['userName'];
        $email = $_POST['userEmail'];
        $role = $_POST['userRole'];

        $roleName = RETURN_STAFF_ROLE($dbh, $role);

        $emailError = eregi('^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z.]{2,5}$', $email);
        
        if (!$emailError){
                return "Error: Incorrect email address format.";
        }

	
	$queryDuplicate = "SELECT UserName, Email FROM tours_Users WHERE 1";
	$results = $dbh->getAll($queryDuplicate);
	foreach ($results as $result){
		$UserID = $result[0];
		$EmailCheck = $result[1];
		if ($UserID == $uid){
			return "Error: $uid is already registered with the system.  User not Added.";
		}
		if ($EmailCheck == $email){
			return "Error: $email is already registered with the system.  User not Added.";
		}
	}

        $query = "INSERT into tours_Users (UserName,Name,PWD,Email,Role) VALUES('$uid', '$name', '$pwd', '$email', '$role')";
        $q = $dbh->query($query);

        if(DB::isError($q)){
                LOG_SQL_ERROR($query, 'ADD_USER');
                return "Error Adding User";
        }
        $msg = "User successfully added to system";
        return $msg;
}//end of ADD_USER
###########################################################################################################

/****
 * Set the active status of a staff member to N
 ****/

function DEACTIVATE_STAFF ($dbh, $removeIDX){
        $query = "UPDATE tours_Staff SET Active='N' WHERE Staff_IDX = \"$removeIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Deactivate_Staff');
                return "Error Deactivating Staff";
        }
        else{
                return "Staff Removed From Active List";
        }


}
#############################################################################

/****
 * Set the active status of a user to N
 ****/

function DEACTIVATE_USER ($dbh, $removeIDX){
        $query = "DELETE FROM tours_Users WHERE Users_IDX = \"$removeIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Deactivate_User');
                return "Error Deactivating User";
        }
        else{
                return "User Login Access Removed";
        }


}

#############################################################################

/****
 * Set the active status of a staff member to Y
 ****/

function REACTIVATE_STAFF ($dbh, $IDX){
        $query = "UPDATE tours_Staff SET Active='Y' WHERE Staff_IDX = \"$IDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Reactivate_Staff');
                return "Error Reactivating Staff";
        }
        else{
                return "Staff Added to Active List";
        }


}

#############################################################################

/****
 * Delete a staff member from the system
 ****/

function DELETE_STAFF ($dbh, $deleteIDX){
        $query = "DELETE FROM  tours_Staff WHERE Staff_IDX = \"$deleteIDX\" LIMIT 1";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Delete_Staff');
                return "Error Deleteing Staff";
        }
       else{
		$query2 = "DELETE FROM tours_Tour_Assign WHERE Staff_IDX = \"$deleteIDX\"";
		$q1 = $dbh->query($query2);
		if (DB::isError($q2)){
			LOG_SQL_ERROR($query2, 'DELETE_STAFF_ASSINGMENTS');
		}
		else{
			return "Staff and corresponding staff assignments deleted from system";
		}
	}


}

#############################################################################

/****
 * Set the active status of a user to Y
 ****/

function REACTIVATE_USER ($dbh, $IDX){
        $query = "UPDATE tours_Users SET Active='Y' WHERE Users_IDX = \"$IDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Reactivate_User');
                return "Error Reactivating User";
        }
        else{
                return "User Added to Active List";
        }


}

#############################################################################

/****
 * Delete a user from the system
 ****/

function DELETE_USER ($dbh, $deleteIDX){
        $query = "DELETE FROM tours_Users WHERE Users_IDX = \"$deleteIDX\" LIMIT 1";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Delete_User');
                return "Error Deleting User";
        }
        else{
                return "User Deleted From System";
        }


}

#############################################################################

/****
 * Delete a tour from the system
 ****/

function DELETE_TOUR ($dbh, $deleteIDX){
	$query = "SELECT Request_IDX FROM tours_PSTRequests WHERE Assigned_Tour_IDX = '$deleteIDX'";
	$AssignedPST = $dbh->getOne($query);

	$query = "SELECT Group_IDX FROM tours_GroupRequests WHERE Assigned_Tour_IDX = '$deleteIDX'";
	$AssignedG = $dbh->getOne($query);

	if (!$AssignedPST && !$AssignedG){
        	$query = "DELETE FROM tours_Tours WHERE Tours_IDX = \"$deleteIDX\" LIMIT 1";
        	$q = $dbh->query($query);
        	if(DB::isError($q)){
                	LOG_SQL_ERROR($query,'Delete_User');
                	return "Error Deleting Tour";
        	}
        	else{
			$query = "DELETE FROM tours_Tour_Assign WHERE Tour_IDX = \"$deleteIDX\"";
			$q = $dbh->query($query);
	                if(DB::isError($q)){
        	                LOG_SQL_ERROR($query,'Delete_User');
                	        return "Error Tour Assignments";
                	}
			else{
                		return "Tour Deleted From System";
			}
        	}
	}
	else{
		return "Requests assigned to tour. Cannot Delete a tour from the system while requests still added to it";
	}


}

#############################################################################

/****
 * Set the active status of a PST request to N
 ****/

function DEACTIVATE_REQUEST ($dbh, $removeIDX){
        $query = "UPDATE tours_PSTRequests SET Active='N' WHERE Request_IDX = \"$removeIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Deactivate_User');
                return "Error Removing Request";
        }
        else{
                return "Request Removed From Active List";
        }


}

#############################################################################

/****
 * Set the active status of a group request to N
 ****/

function DEACTIVATE_GROUP_REQUEST ($dbh, $removeGroupIDX){
        $query = "UPDATE tours_GroupRequests SET Active='N' WHERE Group_IDX = \"$removeGroupIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Deactivate_Group_request');
                return "Error Removing Group Request";
        }
        else{
                return "Group Request Removed From Active List";
        }


}
###############################################################################

/****
 * Set the active status of a PST request to Y
 ****/

function REACTIVATE_REQUEST ($dbh, $reactivateIDX){
        $query = "UPDATE tours_PSTRequests SET Active='Y' WHERE Request_IDX = \"$reactivateIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Deactivate_User');
                return "Error Reactivating Request";
        }
        else{
                return "Request added to the Active List";
        }


}
###############################################################################

/****
 * Set the active status of a group request to Y
 ****/

function REACTIVATE_GROUP_REQUEST ($dbh, $reactivateGroupIDX){
        $query = "UPDATE tours_GroupRequests SET Active='Y' WHERE Group_IDX = \"$reactivateGroupIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Reactivate_Grop_Request');
                return "Error Reactivating Group Request";
        }
        else{
                return "Group Request added to the Active List";
        }


}

#############################################################################

/****
 * Delete a PST request from the system
 ****/

function DELETE_REQUEST ($dbh, $deleteIDX){
	$query2 = "DELETE FROM tours_Clients WHERE Request_IDX = \"$deleteIDX\"";
	$q2 = $dbh->query($query2);
	if (DB::isError($q2)){
		LOG_SQL_ERROR($query2, 'DELETE_PROSPECTIVES');
		return "Error Deleting Prospective Students";
	}
	else{
		$query = "DELETE FROM tours_PSTRequests WHERE Request_IDX = \"$deleteIDX\" LIMIT 1";
		$q = $dbh->query($query);
		if(DB::isError($q)){
			LOG_SQL_ERROR($query,'Delete_User');
			return "Error Deleting Request";
		}
        	else{
        	        return "Request Deleted From System";
        	}
	}
}
#############################################################################

/****
 * Delete a group request from the system
 ****/

function DELETE_GROUP_REQUEST ($dbh, $deleteGroupIDX){
        $query = "DELETE FROM tours_GroupRequests WHERE Group_IDX = \"$deleteGroupIDX\" LIMIT 1";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Delete_Group_Request');
                return "Error Deleting Group Request";
        }
        else{
                return "Group Request Deleted From System";
        }


}

#############################################################################

/****
 * Set the active status of a tour to Y
 ****/
function REACTIVATE_TOUR ($dbh, $reactivateIDX){
        $query = "UPDATE tours_Tours SET Active='Y' WHERE Tours_IDX = \"$reactivateIDX\"";
        $q = $dbh->query($query);
        if(DB::isError($q)){
                LOG_SQL_ERROR($query,'Deactivate_User');
                return "Error Reactivating Tour";
        }
        else{
                return "Tour added to the Active List";
        }


}

#############################################################################

/****
 * Insert a Group Request into the system
 ****/

function INSERT_GROUP_REQUEST($dbh, $POST){

        $groupType = $_POST['groupType'];
        $FirstNameLeader = $_POST['FirstNameLeader'];
        $LastNameLeader = $_POST['LastNameLeader'];
        $GroupName = $_POST['GroupName'];
        $GroupCity = $_POST['GroupCity'];
        $GroupProvState = $_POST['GroupProvState'];
	$GroupCountry = $_POST['GroupCountry'];
        $GroupEmail = $_POST['GroupEmail'];
        $GroupEmailConfirm = $POST['GroupEmailConfirm'];
        $GroupPhone = $_POST['GroupPhone'];
        $GroupGR12 = $_POST['GroupGR12'];
        $GroupGR11 = $_POST['GroupGR11'];
        $GroupGR10 = $_POST['GroupGR10'];
        $GroupGR09 = $_POST['GroupGR09'];
        $GroupAdults = $_POST['GroupAdults'];
        $GroupGrads = $_POST['GroupGrads'];
        $GroupOther = $_POST['GroupOther'];
        $Presentation = $_POST['Presentation'];
        $StudentPanel = $_POST['StudentPanel'];
	$Activity = $_POST['Activity'];
	$Tour_Date = $_POST['Tour_Date'];
	$GroupDescription = $_POST['GroupDescription'];
	$hour = $_POST['hour'];
	$minute = $_POST['minute'];
	$TourTime = "$hour:$minute:00";

        $query = "INSERT INTO tours_GroupRequests (GroupType,FirstNameLeader,LastNameLeader,GroupName,GroupCity,GroupProvState,GroupEmail,GroupPhone,GroupDescription,GroupGR12,GroupGR11,GroupGR10,GroupGR09,GroupAdults,GroupGrads,GroupOther,Presentation,StudentPanel,Tour_Date,TourTime,GroupCountry,Activity) ";
	$query .= "VALUES('$groupType', '$FirstNameLeader', '$LastNameLeader', '$GroupName', '$GroupCity', '$GroupProvState', '$GroupEmail', '$GroupPhone', '$GroupDescription', '$GroupGR12', '$GroupGR11', '$GroupGR10', '$GroupGR09', '$GroupAdults', '$GroupGrads', '$GroupOther', '$Presentation', '$StudentPanel', '$Tour_Date', '$TourTime', '$GroupCountry', '$Activity')";
        $q = $dbh->query($query);

        if(DB::isError($q)){
                LOG_SQL_ERROR($query, 'Insert_Group_Request');
                return "Error Adding Group Request";
        }

        if (file_exists("csv/GroupTourRequests.csv")==0){
                $fp = @fopen("csv/GroupTourRequests.csv", "w");
                $headings = "Group Type,Group Name,First Name,Last Name,Request Date,Tour Time,Email,Phone,City,ProvState,Country,# of Gr12,# of Gr11,# of Gr10,# of Gr9,# of Adults,# of Grads,# of Others,Presentation,Student Panel,Activity,Group Description \n\r";
                fwrite($fp, $headings);
                $results = "\"$groupType\",\"$GroupName\",\"$FirstNameLeader\",\"$LastNameLeader\",\"$Tour_Date\",\"$TourTime\",\"$GroupEmail\",\"$GroupPhone\",\"$GroupCity\",\"$GroupProvState\",\"$GroupCountry\",\"$GroupGR12\",\"$GroupGR11\",\"$GroupGR10\",\"$GroupGR09\",\"$GroupAdults\",\"$GroupGrads\",\"$GroupOther\",\"$Presentation\",\"$StudentPanel\",\"$Activity\",\"$GroupDescription\", \r\n";
                fwrite($fp, $results);
        }
        else{
                $fp = @fopen("csv/GroupTourRequests.csv", "a"); 
                $results = "\"$groupType\",\"$GroupName\",\"$FirstNameLeader\",\"$LastNameLeader\",\"$Tour_Date\",\"$TourTime\",\"$GroupEmail\",\"$GroupPhone\",\"$GroupCity\",\"$GroupProvState\",\"$GroupCountry\",\"$GroupGR12\",\"$GroupGR11\",\"$GroupGR10\",\"$GroupGR09\",\"$GroupAdults\",\"$GroupGrads\",\"$GroupOther\",\"$Presentation\",\"$StudentPanel\",\"$Activity\",\"$GroupDescription\", \r\n";
		fwrite($fp, $results);
        }
        fclose($fp);


        $msg = "Group request added to system.  When the request is processed, you will be e-mailed for confirmation";
        return $msg;
}//end of INSERT_GROUP_REQUEST
#########################################################################

/****
 * Update the attributes of a PST request
 ****/

function UPDATE_SINGLE_REQUEST($dbh, $POST){
	//CLEAN AND REPLACE ALL POST ANSWERS
	foreach ($_POST as $key => $value){
		$value = CLEAN_ANSWER($value);
		$_POST[$key] = $value;
	}

	$Request_Date = $_POST['input9'];
        $First_Name = $_POST['First_Name'];
        $Last_Name = $_POST['Last_Name'];
        $Phone = $_POST['Phone'];
        $Email = $_POST['Email'];
        $Program = $_POST['Program'];
        $Role = $_POST['Role'];
        $School_Level = $_POST['School_Level'];
        $Stu_Num = $_POST['Stu_Num'];
        $City = $_POST['City'];
        $ProvState = $_POST['ProvState'];
	$Country = $_POST['Country'];
        $Forwarded = $_POST['Forwarded'];
        $Accom = $_POST['Accom'];
        $Comments = $_POST['Comments'];
	$updateIDX = $_POST['updateIDX'];
	$NumOfAttendees = $_POST['NumOfAttendees'];
	$NumOfYouths = $_POST['NumOfYouths'];
	$NumOfAdults = $_POST['NumOfAdults'];
	$prospective1 = $_POST['prospective1'];
	$prospective2 = $_POST['prospective2'];
	$prospective3 = $_POST['prospective3'];
	$pr1 = $_POST['pr1'];
	$pr2 = $_POST['pr2'];
	$pr3 = $_POST['pr3'];

	if (($NumOfAdults + $NumOfYouths) != ($NumOfAttendees)){
		return "Error: Number of Youths + Number of Adults MUST equal Number of Attendees.  Request NOT updated.";
	}

	$query = "UPDATE tours_PSTRequests SET Country = '$Country', Request_Date = '$Request_Date', First_Name = '$First_Name', Last_Name = '$Last_Name', Program = '$Program', Role = '$Role', School_Level = '$School_Level', Phone = '$Phone', Stu_Num = '$Stu_Num', City = '$City', ProvState = '$ProvState', Email = '$Email', Forwarded = '$Forwarded', Accom = '$Accom', Comments = '$Comments', NumOfAttendees = '$NumOfAttendees', NumOfYouths = '$NumOfYouths', NumOfAdults = '$NumOfAdults' WHERE Request_IDX = '$updateIDX'";

	$q = $dbh->query($query);
        if(DB::isError($q1)){
                LOG_SQL_ERROR($query,'Edit_Single_Request');
                return "Error Updating Request";
        }
        else{

/****************************
 * Propective Student Clients
 ****************************
 *  $pr is the IDX of the prospective student currently
 *  associated with the request.
 *  $prospective is the new value.
 ****************************/

/**************
 * If there is a prospective student #1 for the request,
 * and it is to be replaced with a blank value, then delete
 * prospective student 1, otherwise, update it with the new value
 **************/
		if ($pr1){
			if ($prospective1 == ""){
				$query = "DELETE FROM tours_Clients WHERE Client_IDX = '$pr1'";
               		        $q1 = $dbh->query($query);
                	        if(DB::isError($q1)){
                	                LOG_SQL_ERROR($query,'Edit_Single_Request');
                	                return "Error Deleting Prospectives";
                	        }
			}
			else{
				$query = "UPDATE tours_Clients SET Name = '$prospective1' WHERE Client_IDX = '$pr1'";
				$q1 = $dbh->query($query); 
		        	if(DB::isError($q1)){
        	        		LOG_SQL_ERROR($query,'Edit_Single_Request');
        			        return "Error Updating Request";
        			}
			}
		} 
                if ($pr2){
                        if ($prospective2 == ""){
                                $query = "DELETE FROM tours_Clients WHERE Client_IDX = '$pr2'";
                                $q1 = $dbh->query($query);
                                if(DB::isError($q1)){
                                        LOG_SQL_ERROR($query,'Edit_Single_Request');
                                        return "Error Deleting Prospectives";
                                }
                        }
                        else{

                        	$query = "UPDATE tours_Clients SET Name = '$prospective2' WHERE Client_IDX = '$pr2'";
                        	$q1 = $dbh->query($query);
		        	if(DB::isError($q1)){
		        	        LOG_SQL_ERROR($query,'Edit_Single_Request');
        			        return "Error Updating Request";
        			}
			}
                }
                if ($pr3){
                        if ($prospective3 == ""){
                                $query = "DELETE FROM tours_Clients WHERE Client_IDX = '$pr3'";
                                $q1 = $dbh->query($query);
                                if(DB::isError($q1)){
                                        LOG_SQL_ERROR($query,'Edit_Single_Request');
                                        return "Error Deleting Prospectives";
                                }
                        }
                        else{

                        	$query = "UPDATE tours_Clients SET Name = '$prospective3' WHERE Client_IDX = '$pr3'";
                        	$q1 = $dbh->query($query);
		        	if(DB::isError($q1)){
        			        LOG_SQL_ERROR($query,'Edit_Single_Request');
                			return "Error Updating Request";
        			}
			}
                }
/***********
 * If there is a vlue to be updated for
 * prospective student 1, but prospective student 1
 * for this request does not exist, then insert 
 * them into the clients table
 ***********/
		if ($prospective1 && !$pr1){
			$query = "INSERT INTO tours_Clients(Name,Request_IDX,Prospective) ";
        		$query .= "VALUES('$prospective1', '$updateIDX', '1')";
			$q1 = $dbh->query($query);
		        if(DB::isError($q1)){
                		LOG_SQL_ERROR($query,'Edit_Single_Request');
                		return "Error Updating Request";
        		}

		}
                if ($prospective2 && !$pr2){
                        $query = "INSERT INTO tours_Clients(Name,Request_IDX,Prospective) ";
                        $query .= "VALUES('$prospective2', '$updateIDX', '1')";
                        $q1 = $dbh->query($query);
		        if(DB::isError($q1)){
        		        LOG_SQL_ERROR($query,'Edit_Single_Request');
                		return "Error Updating Request";
        		}

                }
                if ($prospective3 && !$pr3){
                        $query = "INSERT INTO tours_Clients(Name,Request_IDX, Prospective) ";
                        $query .= "VALUES('$prospective3', '$updateIDX', '1')";
                        $q1 = $dbh->query($query);
		        if(DB::isError($q1)){
        		        LOG_SQL_ERROR($query,'Edit_Single_Request');
        		        return "Error Updating Request";
        		}

                }


                return "Request Updated";
        }
}
#############################################################################

/****
 * Update the attributes of a Group Request
 ****/

function UPDATE_GROUP_REQUEST($dbh, $POST){
        foreach ($_POST as $key => $value){
                $value = CLEAN_ANSWER($value);
                $_POST[$key] = $value;
        }

	$groupIDX = $_POST['groupIDX'];
	$Tour_Date = $_POST['input9'];
	$GroupType = $_POST['GroupType'];
	$GroupName = $_POST['GroupName'];
	$FirstNameLeader = $_POST['FirstNameLeader'];
	$LastNameLeader = $_POST['LastNameLeader'];
	$GroupCity = $_POST['GroupCity'];
	$GroupProvState = $_POST['GroupProvState'];
	$GroupCountry = $_POST['GroupCountry'];
	$GroupEmail = $_POST['GroupEmail'];
	$GroupPhone = $_POST['GroupPhone'];
	$Presentation = $_POST['Presentation'];
	$StudentPanel = $_POST['StudentPanel'];
	$GroupGR12 = $_POST['GR12'];
	$GroupGR11 = $_POST['GR11'];
	$GroupGR10 = $_POST['GR10'];
	$GroupGR09 = $_POST['GR09'];
	$GroupAdults = $_POST['Adults'];
	$GroupGrads = $_POST['Grads'];
	$GroupOther = $_POST['Other'];
	$GroupDescription = $_POST['GroupDescription'];
	$Activity = $_POST['Activity'];


	$numTest12 = eregi('^[0-9]*$', $GroupGR12);
        $numTest11 = eregi('^[0-9]*$', $GroupGR11);
        $numTest10 = eregi('^[0-9]*$', $GroupGR10);
        $numTest9 = eregi('^[0-9]*$', $GroupGR09);
        $numTestG = eregi('^[0-9]*$', $GroupGrads);
        $numTestA = eregi('^[0-9]*$', $GroupAdults);
        $numTestO = eregi('^[0-9]*$', $GroupOther);
	
	

	$hour = $_POST['hour'];
	$minute = $_POST['minute'];
	$TourTime = "$hour:$minute:00"; 
	

        $query = "UPDATE tours_GroupRequests SET GroupCountry = '$GroupCountry', TourTime = '$TourTime', Tour_Date = '$Tour_Date', FirstNameLeader = '$FirstNameLeader', LastNameLeader = '$LastNameLeader', GroupType = '$GroupType', GroupName = '$GroupName', GroupCity = '$GroupCity', GroupProvState = '$GroupProvState', GroupEmail = '$GroupEmail', GroupPhone = \"$GroupPhone\", GroupDescription = \"$GroupDescription\", Presentation = '$Presentation', StudentPanel = '$StudentPanel', Activity = '$Activity', GroupGR12 = '$GroupGR12', GroupGR11 = '$GroupGR11', GroupGR10 = '$GroupGR10', GroupGR09 = '$GroupGR09', GroupAdults = '$GroupAdults', GroupGrads = '$GroupGrads', GroupOther = '$GroupOther' WHERE Group_IDX = '$groupIDX'";

	if ($numTest12 && $numTest11 && $numTest10 && $numTest9 && $numTestG && $numTestA && $numTestO){
        	$q = $dbh->query($query);
        	if(DB::isError($q)){
        	        LOG_SQL_ERROR($query,'Edit_Single_Request');
        	        return "Error Updating Group Request";
        	}
        	else{
        	        return "Group Request Updated<br>";
        	}
	}
	else{
		return "Error: Request NOT Updated. Please use numerical input for numbers of attendees";
	}


}
###########################################################################
function TOUR_COMMENT($dbh,$Tours_IDX,$Comments){

/****
 * Add a comment to a tour
 ****/
	
	$query = "UPDATE tours_Tours SET Comments = '$Comments' WHERE Tours_IDX = '$Tours_IDX'";
	$q = $dbh->query($query);
	if (DB::isError($q)){
		LOG_SQL_ERROR($query,'Update_Tour_Comment');
		return "Error Updaing Staff Comments";
	}
	else{
		return "Tour Comments Updated";
	}



}
###########################################################################

/****
 * Update the emails outlines used as 
 * email templates
 ****/

function UPDATE_EMAILS($dbh, $POST){
	        foreach ($_POST as $key => $value){
        	        $value = CLEAN_HTML_ANSWER($value);
        	        $_POST[$key] = $value;
        	}

		$sub[1] = $_POST['Subject1'];
		$msg[1] = $_POST['MessageBody1'];
		$sub[2] = $_POST['Subject2'];
		$msg[2] = $_POST['MessageBody2'];
		$sub[3] = $_POST['Subject3'];
		$msg[3] = $_POST['MessageBody3'];
		$sub[5] = $_POST['Subject4'];
		$msg[5] = $_POST['MessageBody4'];
		$sub[6] = $_POST['Subject5'];
		$msg[6] = $_POST['MessageBody5'];
		$sub[7] = $_POST['Subject6'];
		$msg[7] = $_POST['MessageBody6'];
		$SigBody = $_POST['SigBody'];

		for ($x = 1; $x <= 3; $x++){
			$query = "UPDATE tours_Email_Templates SET Subject = '$sub[$x]', Body = '$msg[$x]' WHERE Email_IDX = '$x'";
			$q = $dbh->query($query);
	        	if (DB::isError($q)){
        	        	LOG_SQL_ERROR($query,'Update_Emails');
                		return "Error Updating Email Templates";
        		}
		}
                for ($x = 5; $x <= 7; $x++){
                        $query = "UPDATE tours_Email_Templates SET Subject = '$sub[$x]', Body = '$msg[$x]' WHERE Email_IDX = '$x'";
                        $q = $dbh->query($query);
                        if (DB::isError($q)){
                                LOG_SQL_ERROR($query,'Update_Emails');
                                return "Error Updating Email Templates";
                        }
                }
                $query = "UPDATE tours_Email_Templates SET Body = '$SigBody' WHERE Email_IDX = '8'";
                $q = $dbh->query($query);
                if (DB::isError($q)){
                        LOG_SQL_ERROR($query,'Update_Emails');
                        return "Error Updating Email Templates";
                }



/*                $query = "UPDATE tours_Email_Templates SET Subject = '$sub2', Body = '$msg2' WHERE Email_IDX = '2'";
                $q = $dbh->query($query);
                if (DB::isError($q)){
                        LOG_SQL_ERROR($query,'Update_Emails');
                        return "Error Updating Email Templates";
                }
                $query = "UPDATE tours_Email_Templates SET Subject = '$sub3', Body = '$msg3' WHERE Email_IDX = '3'";
                $q = $dbh->query($query);
                if (DB::isError($q)){
                        LOG_SQL_ERROR($query,'Update_Emails');
                        return "Error Updating Email Templates";
                }

                $query = "UPDATE tours_Email_Templates SET Subject = '$sub4', Body = '$msg4' WHERE Email_IDX = '5'";
                $q = $dbh->query($query);
                if (DB::isError($q)){
                        LOG_SQL_ERROR($query,'Update_Emails');
                        return "Error Updating Email Templates";
                }
                $query = "UPDATE tours_Email_Templates SET Subject = '$sub5', Body = '$msg5' WHERE Email_IDX = '6'";
                $q = $dbh->query($query);
                if (DB::isError($q)){
                        LOG_SQL_ERROR($query,'Update_Emails');
                        return "Error Updating Email Templates";
                }

*/

		return "Email Templates Updated Successfully";
}
#################################################################################

/****
 * Update the attributes associated with a user
 ****/

function UPDATE_USER($dbh, $POST){
  	      foreach ($_POST as $key => $value){
        	        $value = CLEAN_ANSWER($value);
                	$_POST[$key] = $value;
        	}

                $Users_IDX = $_POST['Users_IDX'];
                $Name =  $_POST['Name'];
                $UserName =  $_POST['UserName'];
                $PWD =  $_POST['PWD'];
		$PWD2 = $_POST['PWD2'];
                $Email =  $_POST['Email'];
                $Role =  $_POST['Role'];
	
		if ($PWD != $PWD2){
			return "ERROR: Passwords do not match.  Profile was not updated";
		}

                $query = "UPDATE tours_Users SET Name = '$Name',UserName = '$UserName', PWD = '$PWD', Email = '$Email' WHERE Users_IDX = '$Users_IDX'";
                $q = $dbh->query($query);
                if (DB::isError($q)){
                        LOG_SQL_ERROR($query,'Update_Profile');
                        return "Error Updating User Profile";
                }

		return "Profile Successfully Updated";
}
 ############################################################################

/****
 * Update a field to be safe to add into
 * a mysql database
 ****/

function CLEAN_ANSWER($msg){

	$msg = str_replace("<" ,"{", $msg);
        $msg = str_replace(">" ,"}", $msg);
        $msg = str_replace("\"" ,"``", $msg);
        $msg = str_replace("'" ,"`", $msg);
        $msg = str_replace(";" ,"|", $msg);
	//$msg = mysql_real_escape_string(trim($msg));
   	return $msg;

}
#################################################################################

/*****
 * Update the attributes associated with a tour
 ****/

function UPDATE_TOUR($dbh, $POST){
        foreach ($_POST as $key => $value){
                $value = CLEAN_ANSWER($value);
                $_POST[$key] = $value;
        }


	$Tours_IDX = $_POST['Tours_IDX'];
	$TourDate = $_POST['input9'];
	$Type = $_POST['Type'];
	$Comments = $_POST['Comments'];
	$TourName = $_POST['TourName'];
	$NumAttended = $_POST['NumAttended'];

        $query = "UPDATE tours_Tours SET Date = '$TourDate',Type = '$Type', Comments = '$Comments', TourName = '$TourName',NumAttended = '$NumAttended' WHERE Tours_IDX = '$Tours_IDX'";
        $q = $dbh->query($query);
        if (DB::isError($q)){
        	LOG_SQL_ERROR($query,'UPDATE_TOUR');
        	return "Error Updating Tour Info";
        }

	$PST = $_POST['PST'];
	$GroupR = $_POST['GroupR'];
	if ($PST){
	        $NumOfAttendees = $_POST['NumOfAttendees'];
	        $NumOfAdults = $_POST['NumOfAdults'];
	        $NumOfYouths = $_POST['NumOfYouths'];
        	$IDXs = $_POST['IDXs'];
        	$Appts = $_POST['appts'];
        	$numbs = count($NumOfAttendees);
        	for ($x = 0; $x < $numbs; $x++){
        		$NoAd = $NumOfAdults[$x];
        	        $NoY = $NumOfYouths[$x];
        	        $NoAt = $NumOfAttendees[$x];
        	        $ID = $IDXs[$x];
        	        $Appt = $Appts[$x];
			if ($NoAd + $NoY != $NoAt){
				return "Error updating attendee values.  Number of Youths + Number of Adults MUST equal the Number of Attendees";
			}
        	        $upmsg = UPDATE_ATTENDEES($dbh,$ID,$NoAt,$NoAd,$NoY,$Appt);
        	}
	}

	if ($GroupR){
		$Gr12 = $_POST['Gr12'];
		$Gr11 = $_POST['Gr11'];
		$Gr10 = $_POST['Gr10'];
		$Gr09 = $_POST['Gr09'];
		$Adults = $_POST['Adults'];
		$Grads = $_POST['Grads'];
		$Other = $_POST['Other']; 
                $GIDs = $_POST['GIDs'];
                $Appts = $_POST['appts'];
                $numbs = count($GIDs);
                for ($x = 0; $x < $numbs; $x++){
                       	$ID = $GIDs[$x];
			$G12 = $Gr12[$x];
			$G11 = $Gr11[$x];
			$G10 = $Gr10[$x];
			$G09 = $Gr09[$x];
			$Gr = $Grads[$x];
			$Adu = $Adults[$x];
			$Ot = $Other[$x];
			$nmsg .= "$ID,$G12,$G11,$G10,$G09,$Gr,$Adu,$Ot<br>";
			$query = "UPDATE tours_GroupRequests SET GroupGR12 = '$G12', GroupGR11 = '$G11',GroupGR10 = '$G10',GroupGR09 = '$G09',GroupGrads = '$Gr',GroupAdults = '$Adu', GroupOther = '$Ot' WHERE Group_IDX = '$ID'";
		        $q = $dbh->query($query);
        		if (DB::isError($q)){
                		LOG_SQL_ERROR($query,'UPDATE_GroupNums');
                		return "Error Updating Group Numbers";
        		}

               	}

    
	}


	
	return "Tour Updated";
}
###############################################################################

/****
 * Update an the appointment flag for a request
 ****/

function UPDATE_APPOINT($dbh, $IDX, $num){
	$query = "UPDATE tours_PSTRequests SET Appointment = '$num' WHERE Request_IDX = '$IDX'";
        $q = $dbh->query($query);                
	if (DB::isError($q)){                        
		LOG_SQL_ERROR($query,'UPDATE_APPOINT');                        
		return "Error Updating Appointment Status";                
	}
	return "Update Successful";
}
################################################################################

/****
 * Clean an input to be inserted into a mysql table
 * but leave in any characters assoicated with html
 ****/

function CLEAN_HTML_ANSWER($msg){ 
        $msg = str_replace("'" ,"`", $msg);
        $msg = str_replace(";" ,"|", $msg);
        return $msg;
}
#################################################################################
function UPDATE_ATTENDEES($dbh,$IDX,$NoAt,$NoAd,$NoY,$Apt){


/****
 * Update variables associated with a tour
 * changed from the view tours page
 ****/

	if ($Apt == "on"){
		$Apt = 1;
	}
	else{
		$Apt = 0;
	}
        $query = "UPDATE tours_PSTRequests SET NumOfAttendees = '$NoAt', NumOfYouths = '$NoY', NumOfAdults = '$NoAd', Appointment = '$Apt' WHERE Request_IDX = '$IDX'";
        $q = $dbh->query($query);
        if (DB::isError($q)){
                LOG_SQL_ERROR($query,'UPDATE_APPOINT');
                return "Error Updating Attendee Numbers";
        }
        return "Tour Values Updated Successfully";

}
#######################################################
function GET_REQUEST_INFO($dbh, $WHERE){

/****
 * A shortcut for retieving info about a PST request
 ****/

	$query = "SELECT Request_IDX,Processed,Request_Date,First_Name,Last_Name,Program,Role,School_Level,Phone,Stu_Num,City,ProvState,Email,Forwarded,Accom,Comments,Attended, Assigned_Tour_IDX, NumOfAttendees, NumOfAdults, NumOfYouths, Country FROM tours_PSTRequests ";
	$query .= $WHERE;
	$results = $dbh->getAll($query);
	return $results;

}
#######################################################
function GET_TOUR_NUMS($dbh,$Tours_IDX){
	$count = 0;
        $query = "SELECT NumOfAttendees FROM tours_PSTRequests WHERE Assigned_Tour_IDX = '$Tours_IDX'";
        $results = $dbh->getAll($query);
        foreach ($results as $result){
        	$count = $count + $result[0];
        }

        $query = "SELECT GroupGR12, GroupGR11, GroupGR10, GroupGR09, GroupAdults, GroupGrads, GroupOther FROM tours_GroupRequests WHERE Assigned_Tour_IDX = '$Tours_IDX'";
        $results = $dbh->getAll($query);
        foreach ($results as $result){
        	$count = $count + $result[0];
        	$count = $count + $result[1];
        	$count = $count + $result[2];
  	      	$count = $count + $result[3];
        	$count = $count + $result[4];
                $count = $count + $result[5];
                $count = $count + $result[6];
        }
	return $count;
}
#######################################################
function GET_TOUR_DATE($dbh,$Tours_IDX){
	$query = "SELECT Date FROM tours_Tours WHERE Tours_IDX = '$Tours_IDX'";
	$Tour_Date = $dbh->getOne($query);
	return $Tour_Date;
}
?>
