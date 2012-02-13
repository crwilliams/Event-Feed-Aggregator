<?php
# Copyright (c) 2012 Christopher Gutteridge / University of Southampton
# License: GPL

# This file is part of Event Feed Aggregator.

# Event Feed Aggregator is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

# Event Feed Aggregator is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.


require_once( "/var/wwwsites/phplib/arc/ARC2.php" );
require_once( "/var/wwwsites/phplib/Graphite.php" );
error_reporting(E_ALL ^ E_DEPRECATED);

global $diary_config;
$diary_config = array();
$diary_config["path"] = "/home/diary";
