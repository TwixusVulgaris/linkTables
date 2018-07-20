#!/usr/bin/php
<?php
//Настройки
$dtFile = 'dt.txt';
//$bookmarksFile = $adv[2];

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
            if ($minX == -1 || $minX > $latLng->lng) {$minX = $latLng->lng;}
            if ($maxX == -1 || $maxX < $latLng->lng) {$maxX = $latLng->lng;}
            if ($minY == -1 || $minY > $latLng->lat) {$minY = $latLng->lat;}
            if ($maxY == -1 || $maxY < $latLng->lat) {$maxY = $latLng->lat;}
            $portal = ['lat'=>$latLng->lat, 'lng'=>$latLng->lng, 'title'=>$latLng->title];
            if (!in_array($portal, $allPortals)) {$allPortals[] = $portal;}
            if ($prev != []) {$allLinks[] = [$prev, $portal];}
            $prev = $portal;
        }
	}
}

//Находим внешние углы нашей матрёшки
foreach ($allPortals as &$portal) {
	if ($portal['lng'] == $minX || $portal['lng'] == $maxX || $portal['lat'] == $minY || $portal['lat'] == $maxY) {
	    //var_dump($portal);
		$outCorners[] = $portal;
		$portal['lat'] = 0;
		$portal['lng'] = 0;
	}
}

//Собираем порталы в грядки
$ridges = [];
foreach ($outCorners as &$portal) {
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
        } else {$findNext = false;}
    }
    $ridges[] = $ridge;
}
//Расставляем грядки в порядке последовательности их закрытия.
$first = -1;
$second = -1;
$last = -1;
foreach ($ridges as $ridgeIdx=>$ridge) {
	if (LinkCount($ridge[0]) == 2) {
		$first = $ridgeIdx;
	} elseif (count($ridge) < 2) { 
		if ($last == -1) {
			$last = $ridgeIdx; 
		} else {$second = $ridgeIdx;}
	} elseif (LinkCount($ridge[count($ridge) - 1]) == 2) {
		$last = $ridgeIdx;
	} else {$second = $ridgeIdx;} 
}
$ridgesOrdered = [];
$ridgesOrdered[] = $ridges[$first];
$ridgesOrdered[] = $ridges[$second];
$ridgesOrdered[] = $ridges[$last];
unset($ridges);

//Формируем массив линков, из которого в конце будем выгружать в csv
//Закрываем самое внутреннее поле.
$linksTable = [];
$linksTable[] = [$ridgesOrdered[2][0], $ridgesOrdered[1][0]];
$linksTable[] = [$ridgesOrdered[1][0], $ridgesOrdered[0][0]];
$linksTable[] = [$ridgesOrdered[0][0], $ridgesOrdered[2][0]];
//Создаём и наполняем массив, хранящий для каждой грядки опорники, с которых на неё идёт линковка
$bases = [];
$bases[] = [$ridgesOrdered[1][0], $ridgesOrdered[2][0]];
$bases[] = [$ridgesOrdered[2][0], $ridgesOrdered[0][count($ridgesOrdered[0]) - 1]];
$bases[] = [$ridgesOrdered[0][count($ridgesOrdered[0]) - 1], $ridgesOrdered[1][count($ridgesOrdered[0]) - 1]];
//Собственно, формируем остальную таблицу линков.
for ($i=0; $i < 3; $i++) { 
	for ($j=1; $j < count($ridgesOrdered[$i]); $j++) { 
		$linksTable[] = [$bases[$i][0], $ridgesOrdered[$i][$j]];
	}
	for ($j=1; $j < count($ridgesOrdered[$i]); $j++) { 
		$linksTable[] = [$bases[$i][1], $ridgesOrdered[$i][$j]];
	}
}

var_dump($linksTable);


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
    	if (($link[0] == $portal1 && $link[1] == $portal2) || ($link[1] == $portal1 && $link[0] == $portal2)) { 
            $linked = true;
    	}
    	if ($linked) {break;}
    }
    return $linked;
}

function LinkCount($portal, $allLinks) {
    $count = 0;
    foreach ($allLinks as $link) {
    	if ($link[0] == $portal || $link[1] == $portal) {
    		$count++;
    	}
    }
    return $count;
}

//Принимает массив линков, и выгружает в csv
function ExportTable($linksTable) {
	//some code
}
?>