<?php
//Настройки
$dtFile = $adv[1];
$bookmarksFile = $adv[2];

$allPortals = [];
$allLinks = [];
$minX = -1;
$minY = -1;
$maxX = -1;
$maxY = -1;
$outCorners = [];

//Читаем джсон из файла
$drawing = json_decode(file_get_contents($dtFile));
//Разбираем полученный джсон. Получаем массив порталов и массив линков
foreach($drawing as $line) {
	if (($line->type == 'polyline')){
		$prev = [];
        foreach($line->latLngs as $latLng) {
            if ($minX == -1 || $minX > $latLng->lng) {$minX = $latLng->lat;}
            if ($maxX == -1 || $maxX < $latLng->lng) {$maxX = $latLng->lat;}
            if ($minY == -1 || $minY > $latLng->lat) {$minY = $latLng->lng;}
            if ($maxY == -1 || $maxY < $latLng->lat) {$maxY = $latLng->lng;}
            $portal = ['lat'=>$latLng->lat, 'lng'=>$latLng->lng];
            if (!in_array($portal, $allPortals)) {$allPortals[] = $portal;}
            if ($prev != []) {$allLinks[] = [$prev, $portal];}
            $prev = $portal;
        }
	}
}
//Находим внешние углы нашей матрёшки и параллельно считаем общее количество линков каждого портала
foreach ($allPortals as $portal) {
	$lnkCount = 0;
	foreach ($allLinks as $link) {
		if ($link[0] == $portal || $link[1] == $portal) {$lnkCount++;}
	}
	$portal['linkCount'] = $lnkCount;
	if ($portal['lng'] == minX || $portal['lng'] == maxX || $portal['lat'] == minY || $portal['lat'] == maxY) {
		$outCorners[] = $portal;
		$portal['lat'] = 0;
		$portal['lng'] = 0; 
	}
}
//Собираем порталы в грядки
$ridges = [];
foreach ($outCorners as $portal) {
	$ridge = [$portal];
	$findNext = true;
	while ($findNext) {
	    $closestIdx = FindClosest($portal, $allPortals);
	    if ($allPortals[$closestIdx]['lat'] != 0 && $allPortals[$closestIdx]['lng'] != 0) {
	        if (!DetectLink($portal, $allPortals[$closestIdx], $allLinks)) {
		        $portal = $allPortals[$closestIdx];
		        array_unshift($ridge, $portal);
		        $allPortals[$closestIdx]['lat'] = 0;
		        $allPortals[$closestIdx]['lng'] = 0;
	        } else {$findNext = false;}
        }
    }
    $ridges[] = $ridge;
}

//

//Находит портал, ближайший к заданному, и возвращает его индекс
function FindClosest($portal1, $allPortals)
{
    $minDist = -1;
    $closestIdx = 0;
    foreach ($allPortals as $idx => $portal2) {
    	//находим квадрат расстояния между порталами
    	$latD = $portal1['lat'] - $portal2['lat'];
	    $lngD = $portal1['lng'] - $portal2['lng'];
	    $dist = $latD*$latD + $lngD*$lngD;
	    if ($minDist == -1 || $dist < $minDist) {
			$closestIdx = $idx;
			$minDist = $dist;
		} 
    }
    return $closestIdx;
}

//Проверяет наличие линка между двумя порталами, возвращает true/false
function DetectLink($portal1, $portal2, $allLinks) {
	$linked = false;
    foreach ($allLinks as $link) {
    	if (($link[0] == $portal1 && $link[1] == $portal2) || ($link[0] == $portal2 && $link[1] == $portal1)) { 
            $linked = true;
    	}
    	if ($linked) {break;}
    }
    return $linked;
}
?>