<?php

$c = include('config.php');
require 'tieba.php';

$t = new TiebaPostMan($c);
$t->cron();
// $t->demaon();