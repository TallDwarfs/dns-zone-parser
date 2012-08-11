<?php
/*
 * BIND / Microsoft DNS zone parser
 * By Jelle Luteijn
 * http://www.jelleluteijn.com

License:

Microsoft Public License (MS-PL)

This license governs use of the accompanying software. If you use the software, you
accept this license. If you do not accept the license, do not use the software.

1. Definitions
The terms "reproduce," "reproduction," "derivative works," and "distribution" have the
same meaning here as under U.S. copyright law.
A "contribution" is the original software, or any additions or changes to the software.
A "contributor" is any person that distributes its contribution under this license.
"Licensed patents" are a contributor's patent claims that read directly on its contribution.

2. Grant of Rights
(A) Copyright Grant- Subject to the terms of this license, including the license conditions and limitations in section 3, each contributor grants you a non-exclusive, worldwide, royalty-free copyright license to reproduce its contribution, prepare derivative works of its contribution, and distribute its contribution or any derivative works that you create.
(B) Patent Grant- Subject to the terms of this license, including the license conditions and limitations in section 3, each contributor grants you a non-exclusive, worldwide, royalty-free license under its licensed patents to make, have made, use, sell, offer for sale, import, and/or otherwise dispose of its contribution in the software or derivative works of the contribution in the software.

3. Conditions and Limitations
(A) No Trademark License- This license does not grant you rights to use any contributors' name, logo, or trademarks.
(B) If you bring a patent claim against any contributor over patents that you claim are infringed by the software, your patent license from such contributor to the software ends automatically.
(C) If you distribute any portion of the software, you must retain all copyright, patent, trademark, and attribution notices that are present in the software.
(D) If you distribute any portion of the software in source code form, you may do so only under this license by including a complete copy of this license with your distribution. If you distribute any portion of the software in compiled or object code form, you may only do so under a license that complies with this license.
(E) The software is licensed "as-is." You bear the risk of using it. The contributors give no express warranties, guarantees or conditions. You may have additional consumer rights under your local laws which this license cannot change. To the extent permitted under your local laws, the contributors exclude the implied warranties of merchantability, fitness for a particular purpose and non-infringement.
 
 Database PowerDNS:

 CREATE TABLE IF NOT EXISTS `domains` (
  `id` int(255) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `master` varchar(128) DEFAULT NULL,
  `last_check` int(11) DEFAULT NULL,
  `type` varchar(6) NOT NULL,
  `notified_serial` int(11) DEFAULT NULL,
  `account` varchar(40) DEFAULT NULL,
  `checksum` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_index` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `records` (
  `id` int(255) NOT NULL AUTO_INCREMENT,
  `domain_id` int(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(6) DEFAULT NULL,
  `content` varchar(255) DEFAULT NULL,
  `ttl` int(11) DEFAULT NULL,
  `prio` int(11) DEFAULT NULL,
  `change_date` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rec_name_index` (`name`),
  KEY `nametype_index` (`name`,`type`),
  KEY `domain_id` (`domain_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `api` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

NOTE: domains table uses a custom field named 'checksum' this field is a VARCHAR with a length of 40 characters (sha1)
NOTE: domains and records tables uses a customized id and domain_id length, this is to be sure that there is enough space
 */

$record_types = array('SOA', 'A', 'NS', 'MX', 'CNAME', 'PTR', 'TXT', 'AAAA', 'SRV');

mysql_connect("localhost","", "");
mysql_select_db("powerdns");

function parse_dns($zone_file, $path = false) {
    global $record_types;
    if(!$path) {
        $file = file_get_contents($zone_file);
    }else{
        $file = file_get_contents($path.$zone_file);
    }
    $file = str_replace("
","\n", $file);
    
    $checksum = sha1($file);

    $raw_array = explode("\n", $file);

    //convert zone file to array - removing empty rowes and spaces
    $convert_array = array();
    $i = 0;
    foreach($raw_array as $v) {
        $trim = trim($v);
        if(!empty($trim)) {
            $data_array = array();
            $v = str_replace("	", " ", $v);
            $data_array[] = $raw_array = explode(" ", $v);
            foreach($raw_array as $v) {
                $trim = trim($v);
                if(empty($trim)) {
                }elseif($v == "IN") {
                }elseif($v == "(") {
                }elseif($v == ")") {
                }else{
                     $convert_array[$i][] = $v;
                }
            }
        }
        $i++;
    }

    //remove comments
    $comment_array = $convert_array;
    $comment = false;
    foreach( $comment_array as $array_k => $array_v) {
        foreach( $array_v as $k => $v) {
            if($v == ";" || $comment == true) {
                unset($convert_array[$array_k][$k]);
                $comment = true;
            }
        }
        if(!isset($convert_array[$array_k][0])) {
            unset($convert_array[$array_k]);
        }
        $comment = false;
    }

    //remove TTL from records except SOA
    $ttl_array = $convert_array;
    $ttl_value = false;
    foreach( $comment_array as $array_k => $array_v) {
        foreach( $array_v as $k => $v) {
            if($v == "\$TTL") {
                $ttl_value = true;
            }elseif($ttl_value == true) {
                $ttl = $v;
                unset($convert_array[$array_k]);
            }
        }
        $ttl_value = false;
        if(isset($ttl)) {
            if(isset($convert_array[$array_k][1]) && $ttl == $convert_array[$array_k][1]) {
                unset($convert_array[$array_k][1]);
            }
        }
    }

    //is the first one a record type if so, replace it with previous record name
    $record_array = $convert_array;
    $record_value = '';
    foreach( $record_array as $k => $v) {
        foreach( $record_types as $type) {
            if($v[0] == $type) {
               $v = array_reverse($v);
               $v[] = $record_value;
               $v = array_reverse($v);
               $convert_array[$k] = $v;
            }
        }

        $record_value = $v[0];
    }
    
    //generate new indexes
    $array = $convert_array;
    $convert_array = array();
    $i = 0;
    foreach( $array as $v1) {
            foreach( $v1 as $v2) {
                $convert_array[$i][] = $v2;
        }
        $i++;
    }
    
    //generate SOA and TXT records
    $zone_array = explode('.',$zone_file);
    $zone_array = array_reverse($zone_array);
    unset($zone_array[0]);
    $zone_array = array_reverse($zone_array);
    $domain = implode('.',$zone_array); 

    $fqdn_array = $convert_array;
    
    foreach( $fqdn_array as $k => $v) {
        if(isset($v[1]) && $v[1] == "SOA") {
            $row = $k;
            $soa = $v[2]. " ".$v[3];
            $k++;
            $soa .= " ".$convert_array[$k][0];
            unset($convert_array[$k][0]);
            $k++;
            $soa .= " ".$convert_array[$k][0];
            $convert_array[$row][3] = $convert_array[$k][0];
            unset($convert_array[$k][0]);
            $k++;
            $soa .= " ".$convert_array[$k][0];
            unset($convert_array[$k][0]);
            $k++;
            $soa .= " ".$convert_array[$k][0];
            unset($convert_array[$k][0]);
            $k++;
            $soa .= " ".$convert_array[$k][0];
            unset($convert_array[$k][0]);

            $convert_array[$row][2] = $soa;
            $convert_array[$row][4] = $checksum;
        }elseif(isset($v[1]) && $v[1] == "TXT") {
            $count = count($v);
            $txt = $v[2];
            for($i = 3; $i < $count; $i++) {
                $txt .= " ".$v[$i];
                unset($convert_array[$k][$i]);
            }
            $convert_array[$k][2] = $txt;
        }
    }

    //generate new indexes
    $array = $convert_array;
    $convert_array = array();
    $i = 0;
    foreach( $array as $v1) {
            foreach( $v1 as $v2) {
                $convert_array[$i][] = $v2;
        }
        $i++;
    }

    //generate FQDN
    $zone_array = explode('.',$zone_file);
    $zone_array = array_reverse($zone_array);
    unset($zone_array[0]);
    $zone_array = array_reverse($zone_array);
    $domain = implode('.',$zone_array); 

    $fqdn_array = $convert_array;
    foreach( $fqdn_array as $k => $v) {
        foreach( $record_types as $type) {
            if(isset($v[1]) && $v[1] == $type) {
               $check_array = explode(".",$v[0]);
               $check_array = array_reverse($check_array);
               if($check_array[0] == '') {
                   unset($check_array[0]);
                   $check_array = array_reverse($check_array);
                   $convert_array[$k][0] = implode('.',$check_array); 
               }elseif($v[0] == "@") {
                   $convert_array[$k][0] = $domain;
               }elseif($v[0] == $domain."."){
                   $convert_array[$k][0] = $domain;
               }else{
                   $convert_array[$k][0] = $v[0].".".$domain;
               }
            }
        }
    }

    //array sorten
    $array = array();
    $i = 0;
    foreach( $convert_array as $v1) {
            foreach( $v1 as $v2) {
                $array[$i][] = $v2;
        }
        $i++;
    }
    return $array;
}

function import_dns($data) {
    //check if domain allready exists and controlled via Bind / Microsoft DNS
    $query = mysql_query("select * from domains where name = '".$data[0][0]."'");
    $id = false;
    if(!mysql_num_rows($query)) {
        mysql_query("insert into domains set name = '".$data[0][0]."', type = 'NATIVE', account = '".$data[0][5]."', checksum = '".$data[0][4]."'");
        $id = mysql_insert_id();
    }else{
        $sql = mysql_fetch_object($query);
        if($sql->account == $data[0][5]) {
            $id = $sql->id;
            if($sql->checksum != $data[0][4]) {
            }else{
                //no changes
                return $id;
            }
        }else{
            //domain allready exists
            return;
        }
    }

    //checksum is different lets do our business
    mysql_query("update domains set checksum = '".$data[0][4]."' where id = '".$id."'");
    
    //delete every record, just to be sure
    mysql_query("delete from records where domain_id = '".$id."'");

    //TTL
    $ttl = $data[0][3];

    //loops through the array
    foreach($data as $record) {
        if($record[1] == "MX") {
            mysql_query("insert into records set content = '".$record[3]."', ttl = '".$ttl."', prio = '".$record[2]."', type = '".$record[1]."', domain_id = '".$id."', name = '".$record[0]."'");
        }else{
            mysql_query("insert into records set content = '".$record[2]."', ttl = '".$ttl."', type = '".$record[1]."', domain_id = '".$id."', name = '".$record[0]."'");
        }
    }

    return $id;
}

function trashcan_dns($id) {
    //removes our unwanted records from the database
    $query = mysql_query("select id from domains WHERE id NOT IN ('". implode("','", $id) ."')");  
    while($sql = mysql_fetch_object($query)) {    
        mysql_query("delete from domains WHERE id = '". $sql->id ."'");
        mysql_query("delete from records WHERE domain_id = '". $sql->id ."'");
    }
}

$zone_data = array();
/*if ($handle = opendir("/var/named")) {
	while (false !== ($file = readdir($handle))) {
		if ($file != "." && $file != "..") {
            $zone_array = explode('.',$file);
            $zone_array = array_reverse($zone_array);
            if($zone_array[0] == "db" || $zone_array[0] == "dns") {
                if($zone_array[1] != "arpa" && $zone_array[1] != "CACHE" && $zone_array[1] != "TrustAnchors") {
                     $zone = parse_dns($file,"/var/named/");
                     $zone[0][5] = 'bind';
                     $zone_data[] = $zone;
                }
            }
		}
	}
	closedir($handle);
}*/

$query = mysql_query("select * from api");
while($sql = mysql_fetch_object($query)) {
    $content = file_get_contents($sql->url);
    $array = json_decode($content);
    
    foreach($array as $key => $zone) {
        $check_zone = $zone;
        unset($check_zone[0][4]);
        $checksum = sha1(json_encode($check_zone));
        
        $zone[0][5] = $sql->account;
        
        if($zone[0][4] == $checksum) {
            $zone_data[] = $zone;
        }  
    }
}

//echo "<pre>".print_r($zone_data, true)."</pre>";

$id = array();
foreach($zone_data as $zone) {
    $id[] = import_dns($zone);
}
trashcan_dns($id);
?>
