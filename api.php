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

mysql_connect("localhost","", "");
mysql_select_db("powerdns");

function fetch_dns($domain_id) {
    $zone = array();
    
    $query = mysql_query("select * from records where type = 'SOA' and domain_id='".$domain_id."'");
    $fetch = mysql_fetch_object($query);
    $zone[0][0] = $fetch->name;
    $zone[0][1] = $fetch->type;
    $zone[0][2] = $fetch->content;
    $zone[0][3] = $fetch->ttl;
    
    $query = mysql_query("select * from records where type != 'SOA' and domain_id='".$domain_id."'");
    $i = 0;
    while($fetch = mysql_fetch_object($query)) {
        $i++;
        if ($fetch->type == "MX") {
            $zone[$i][0] = $fetch->name;
            $zone[$i][1] = $fetch->type;
            $zone[$i][2] = $fetch->prio;
            $zone[$i][3] = $fetch->content;
        }else{
            $zone[$i][0] = $fetch->name;
            $zone[$i][1] = $fetch->type;
            $zone[$i][2] = $fetch->content;
        }
    }
    
    $zone[0][4] = sha1(json_encode($zone));
    
    return $zone;
}

$zone_data = array();
//$query = mysql_query("select * from domains where domains_active = '1'");
$query = mysql_query("select * from domains");
while($sql = mysql_fetch_object($query)) {
    $zone_data[] = fetch_dns($sql->id);
}

//echo "<pre>".print_r($zone_data, true)."</pre>";
echo json_encode($zone_data);

?>
