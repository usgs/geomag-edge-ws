<?php


/**
 * Represents a Geomag Web Service Query.
 *
 * Usually created by GeomagWebService->parseQuery().
 */
class GeomagQuery {

  /** observatory id. */
  public $id = null;

  /** time of first sample, as unix epoch timestamp. */
  public $starttime = null;

  /** time of last sample, as unix epoch timestamp. */
  public $endtime = null;

  /** array of requested elements. */
  public $elements = null;

  /** period between samples in seconds, 60=minute, 1=second. */
  public $sampling_period = 60;

  /** data type, one of 'variation', 'adjusted', 'quasi-definitive', 'definitive' */
  public $type = 'variation';

  /** output format, 'iaga2002' or 'json' */
  public $format = 'iaga2002';

}
