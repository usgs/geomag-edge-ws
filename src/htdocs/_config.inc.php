<?php

$SITE_COMMONNAV =
    navItem('#home', 'Home') .
    navItem('#aboutus', 'About Us') .
    navItem('#contactus', 'Contact Us') .
    navItem('#legal', 'Legal') .
    navItem('#partners', 'Partners');

$THEME_CSS =
  '<link rel="stylesheet" href="/theme/site/geomag/index.css"/>';

$SITE_URL = 'geomag.usgs.gov';
$SITE_SITENAV =
  navItem('#monitoring', 'Monitoring') .
  navItem('#data', 'Data &amp; Products') .
  navItem('#research', 'Research') .
  navItem('#learn', 'Learn') .
  navItem('#services', 'Services') .
  navItem('#partners', 'Partners');

$HEAD = $THEME_CSS .
    // page head content
    ($HEAD ? $HEAD : '');
