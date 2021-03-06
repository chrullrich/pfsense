<?php
/*
 * services_captiveportal_mac.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2004 Dinesh Nair <dinesh@alphaque.com>
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-services-captiveportal-macaddresses
##|*NAME=Services: Captive portal: Mac Addresses
##|*DESCR=Allow access to the 'Services: Captive portal: Mac Addresses' page.
##|*MATCH=services_captiveportal_mac.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

global $cpzone;
global $cpzoneid;

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
	$cpzone = $_POST['zone'];
}
$cpzone = strtolower($cpzone);

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("Services"), gettext("Captive Portal"), $a_cp[$cpzone]['zone'], gettext("MACs"));
$shortcut_section = "captiveportal";

$actsmbl = array('pass' => '<i class="fa fa-check text-success"></i>&nbsp;' . gettext("Pass"),
	'block' => '<i class="fa fa-times text-danger"></i>&nbsp;' . gettext("Block"));

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;

		if (is_array($a_cp[$cpzone]['passthrumac'])) {
			$cpzoneid = $a_cp[$cpzone]['cpzoneid'];
			$rules = captiveportal_passthrumac_configure();
			if (!empty($rules)) {
				@file_put_contents("{$g['tmp_path']}/passthrumac_gui", $rules);
				mwexec("/sbin/ipfw -x {$cpzoneid} {$g['tmp_path']}/passthrumac_gui");
				@unlink("{$g['tmp_path']}/passthrumac_gui");
			}
			$savemsg = get_std_save_message($retval);
			if ($retval == 0) {
				clear_subsystem_dirty('passthrumac');
			}
		}
	}

	if ($_POST['postafterlogin']) {
		if (!is_array($a_passthrumacs)) {
			echo gettext("No entry exists yet!") ."\n";
			exit;
		}

		if (empty($_POST['zone'])) {
			echo gettext("Please set the zone on which the operation should be allowed");
			exit;
		}
		if (!is_array($a_cp[$cpzone]['passthrumac'])) {
			$a_cp[$cpzone]['passthrumac'] = array();
		}
		$a_passthrumacs =& $a_cp[$cpzone]['passthrumac'];

		if ($_POST['username']) {
			$mac = captiveportal_passthrumac_findbyname($_POST['username']);
			if (!empty($mac)) {
				$_POST['delmac'] = $mac['mac'];
			} else {
				echo gettext("No entry exists for this username:") . " " . $_POST['username'] . "\n";
			}
		}

		if ($_POST['delmac']) {
			$found = false;
			foreach ($a_passthrumacs as $idx => $macent) {
				if ($macent['mac'] == $_POST['delmac']) {
					$found = true;
					break;
				}
			}
			if ($found == true) {
				$cpzoneid = $a_cp[$cpzone]['zoneid'];
				$rules = captiveportal_passthrumac_delete_entry($a_passthrumacs[$idx]);
				$uniqid = uniqid("{$cpzone}_mac");
				file_put_contents("{$g['tmp_path']}/{$uniqid}_tmp", $rules);
				mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/{$uniqid}_tmp");
				@unlink("{$g['tmp_path']}/{$uniqid}_tmp");
				unset($a_passthrumacs[$idx]);
				write_config();
				echo gettext("The entry was successfully deleted") . "\n";
			} else {
				echo gettext("No entry exists for this mac address:") . " " . $_POST['delmac'] . "\n";
			}
		}
		exit;
	}
}

if ($_GET['act'] == "del") {
	$a_passthrumacs =& $a_cp[$cpzone]['passthrumac'];

	if ($a_passthrumacs[$_GET['id']]) {
		$cpzoneid = $a_cp[$cpzone]['zoneid'];
		$rules = captiveportal_passthrumac_delete_entry($a_passthrumacs[$_GET['id']]);
		$uniqid = uniqid("{$cpzone}_mac");
		file_put_contents("{$g['tmp_path']}/{$uniqid}_tmp", $rules);
		mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/{$uniqid}_tmp");
		@unlink("{$g['tmp_path']}/{$uniqid}_tmp");
		unset($a_passthrumacs[$_GET['id']]);
		write_config();
		header("Location: services_captiveportal_mac.php?zone={$cpzone}");
		exit;
	}
}

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

if (is_subsystem_dirty('passthrumac')) {
	print_apply_box(gettext("The Captive Portal MAC address configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MACs"), true, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed IP Addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("Vouchers"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);
?>
<div class="table-responsive">
	<table class="table table-hover table-striped table-condensed sortable-theme-bootstrap table-rowdblclickedit" data-sortable>
		<thead>
			<tr>
				<th><?=gettext('Action')?></th>
				<th><?=gettext("MAC address")?></th>
				<th><?=gettext("Description")?></th>
				<th><?=gettext("Actions")?></th>
			</tr>
		</thead>

<?php
if (is_array($a_cp[$cpzone]['passthrumac'])): ?>
		<tbody>
<?php
$i = 0;
foreach ($a_cp[$cpzone]['passthrumac'] as $mac): ?>
			<tr>
				<td>
					<?=$actsmbl[$mac['action']]?>
				</td>
				<td>
					<?=$mac['mac']?>
				</td>
				<td >
					<?=htmlspecialchars($mac['descr'])?>
				</td>
				<td>
					<a class="fa fa-pencil"	title="<?=gettext("Edit MAC address"); ?>" href="services_captiveportal_mac_edit.php?zone=<?=$cpzone?>&amp;id=<?=$i?>"></a>
					<a class="fa fa-trash"	title="<?=gettext("Delete MAC address")?>" href="services_captiveportal_mac.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i?>"></a>
				</td>
			</tr>
<?php
$i++;
endforeach; ?>
		</tbody>
	</table>
<?php
else:
?>
		</tbody>
	</table>
<?php
endif;
?>
</div>

<nav class="action-buttons">
	<a href="services_captiveportal_mac_edit.php?zone=<?=$cpzone?>&amp;act=add" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(gettext('Adding MAC addresses as "pass" MACs allows them access through the captive portal automatically without being taken to the portal page.'), 'info', false); ?>
</div>
<?php
include("foot.inc");
