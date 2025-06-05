<?php

/**
 * @package Rdo
 */

require_once './Clotho.php';

$im = new ItemMapper($conf['adapter']);
$dm = new DependencyMapper($conf['adapter']);
$cm = new CalendarMapper($conf['adapter']);
$rm = new ResourceMapper($conf['adapter']);
$ram = new ResourceAvailabilityMapper($conf['adapter']);

$item = $im->create(['item_name' => 'Test Item', 'item_parent' => 0]);
echo get_class($item) . "\n";
$item = $im->create(['item_name' => 'Test Item 2', 'item_parent' => 0]);
echo get_class($item) . "\n";
$item = $im->create(['item_name' => 'Child Item', 'item_parent' => 1]);
echo get_class($item) . "\n";

$dep = $dm->create(['dependency_type' => 'S', 'dependency_lhs_item' => 1, 'dependency_rhs_item' => 2]);
echo get_class($dep) . "\n";

$cal = $cm->create(['calendar_name' => 'Test Calendar', 'calendar_hoursinday' => 8,
    'calendar_hoursinweek' => 40, 'calendar_type' => 'weekly', 'calendar_data' => '']);
echo get_class($cal) . "\n";

$res = $rm->create(['resource_type' => 'M', 'resource_name' => 'Test Resource', 'resource_base_calendar' => 1]);
echo get_class($res) . "\n";

$resavail = $ram->create(['resource_id' => 1, 'availability_date' => 1121404095, 'availability_hours' => 2]);
echo get_class($resavail) . "\n";
