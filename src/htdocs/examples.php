<?php
include_once '../conf/config.inc.php';

if (!isset($TEMPLATE)) {
  $TITLE = 'More Examples';
  include 'template.inc.php';
}
?>

<dl>
  <dt>Data from observatory BOU (defaults to current day if not specified):</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>One-second data from observatory BOU (defaults to current day if not specified):</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&sampling_period=1';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Adjusted (one-min) data from observatory BOU:</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&type=adjusted';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Quasi-definitive data from observatory BOU for January 2016:</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&type=quasi-definitive&starttime=2016-01-01T00:00:00Z&endtime=2016-01-30T23:59:59Z';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Definitive data from observatory BOU for March 1st, 2014:</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&type=definitive&starttime=2014-03-01T00:00:00Z';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Data from observatory BOU in HDZ (rather than XZY):</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&elements=H,D,Z,F';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Data from observatory BOU in HDZ for March 2016 (variation, one-min):</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&starttime=2016-03-01T00:00:00Z&endtime=2016-03-31T23:59:59Z&elements=H,D,Z,F';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Data for SQ, SV, or Dist for February 2016:</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&starttime=2016-02-01T00:00:00Z&endtime=2016-02-29T23:59:00Z&elements=SQ,SV,DIST';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Delta-F data from observatory BOU:</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&elements=G';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Data for Dst:</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=USGS&elements=DST';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>

  <dt>Satellite data from observatory BOU in HEZF:</dt>
  <dd>
<?php
  $url = $HOST_URL_PREFIX . $MOUNT_PATH . '/?id=BOU&elements=H,E,Z,F&type=R1';
  echo '<a href="' . $url . '">' . $url . '</a>';
?>
  </dd>
</dl>
