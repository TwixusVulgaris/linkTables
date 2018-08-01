#!/usr/bin/php
<?php
//Настройки
$dtFile = 'dt.txt';
$bookmarksFile = 'bookmarks.txt';

$allPortals = [];
$allLinks = [];
$minX = -1;
$minY = -1;
$maxX = -1;
$maxY = -1;
$outCorners = [];
$portalData = [];

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
		$outCorners[] = $portal;
		$portal['lat'] = 0;
		$portal['lng'] = 0;
	}
}

//Формируем массив грядок
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
	if (LinkCount($ridge[0], $allLinks) == 2) {
		$first = $ridgeIdx;
	} elseif (count($ridge) < 2) { 
		if ($last == -1) {
			$last = $ridgeIdx; 
		} else {$second = $ridgeIdx;}
	} elseif (LinkCount($ridge[count($ridge) - 1], $allLinks) == 2) {
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
$linksTable[] = [$ridgesOrdered[2][0], $ridgesOrdered[0][0]];
$linksTable[] = [$ridgesOrdered[1][0], $ridgesOrdered[0][0]];
//Создаём и наполняем массив, хранящий для каждой грядки опорники, с которых на неё идёт линковка
//$bases = [];
//$bases[] = [$ridgesOrdered[1][0], $ridgesOrdered[2][0]];
//$bases[] = [$ridgesOrdered[2][0], $ridgesOrdered[0][count($ridgesOrdered[0]) - 1]];
//$bases[] = [$ridgesOrdered[0][count($ridgesOrdered[0]) - 1], $ridgesOrdered[1][count($ridgesOrdered[1]) - 1]];
$basesT = [];
$basesT[] = [[2, 0], [1, 0]];
$basesT[] = [[1, 0], [0, (count($ridgesOrdered[0]) - 1)]];
$basesT[] = [[0, (count($ridgesOrdered[0]) - 1)], [2, (count($ridgesOrdered[2]) - 1)]];
var_dump($basesT);
//Собственно, формируем остальную таблицу линков.
//for ($i=0; $i < 3; $i++) { 
//	for ($j=1; $j < count($ridgesOrdered[$i]); $j++) { 
//		$linksTable[] = [$bases[$i][0], $ridgesOrdered[$i][$j]];
//	}
//	for ($j=1; $j < count($ridgesOrdered[$i]); $j++) { 
//		$linksTable[] = [$bases[$i][1], $ridgesOrdered[$i][$j]];
//	}
//}
for ($i=0; $i < 3; $i++) { 
	foreach ($basesT[$i] as $basePortal) {
    	for ($j=1; $j < count($ridgesOrdered[$i]); $j++) { 
	    	$linksTable[] = [$ridgesOrdered[$basePortal[0]][$basePortal[1]], $ridgesOrdered[$i][$j]];
	    }
	}
}

//Посчитаем потребное количество ключей от каждого портала
foreach ($ridgesOrdered as &$ridge) {
	foreach ($ridge as &$portal) {
    	$inboundLinks = LinkCount($portal, $linksTable, 'in');
	    $outboundLinks = LinkCount($portal, $linksTable, 'out');
	    $portal['keys'] = $inboundLinks;
	    $portal['linksOut'] = $outboundLinks;
	}
}

//Обрабатываем Bookmarks, берём имена порталов и названия грядок
//Читаем json из файла
$bookmarks = json_decode(file_get_contents($bookmarksFile), true);
//Извлекаем из полученной структуры нужную нам информацию
foreach ($bookmarks['portals'] as $folder) {
	foreach ($folder['bkmrk'] as $key=>$point) {
		$keys = explode(',', $point['latlng']);
        $portalData[$keys[0]][$keys[1]] = [$folder['label'], $point['label']];
	}
}

//Выгружаем всё в csv
ExportTable($linksTable, $ridgesOrdered, $portalData);


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

//Подсчитывает количество линков с участием заданного портала
function LinkCount($portal, $allLinks, $direction = 'all') {
    $count = 0;
    switch ($direction) {
    	case 'in':
            foreach ($allLinks as $link) {
    	        if ($link[1] == $portal) {
        		$count++;
        	    }
            }
    		break;

    	case 'out':
            foreach ($allLinks as $link) {
    	        if ($link[0] == $portal) {
        		$count++;
        	    }
            }
    		break;
    	
    	default:
            foreach ($allLinks as $link) {
    	        if ($link[0] == $portal || $link[1] == $portal) {
    		    $count++;
    	        }
            }
    		break;
    }
    return $count;
}

//Принимает массив линков и массив порталов, и выгружает в csv таблицу линковки и количество ключей для каждого из порталов + количество SBUL, которые нужно установить в каждый из опорников
function ExportTable($linksTable, $ridges, $portalData) {
	$handle = fopen('linktable.csv', 'w');
	$header = ['Номер линка', 'Грядка', 'Портал', 'Ссылка', 'Грядка', 'Портал', 'Ссылка'];
	fputcsv($handle, $header, ',', '"');
	foreach ($linksTable as $idx=>$link) {
		$linkNumber = $idx + 1;
		$srcLnk = "https://ingress.com/intel?ll=".$link[0]['lat'].",".$link[0]['lng']."&z=17&pll=".$link[0]['lat'].",".$link[0]['lng'];
		$dstLnk = "https://ingress.com/intel?ll=".$link[1]['lat'].",".$link[1]['lng']."&z=17&pll=".$link[1]['lat'].",".$link[1]['lng'];
		$srcRidge = $portalData[strval($link[0]['lat'])][strval($link[0]['lng'])][0];
		$dstRidge = $portalData[strval($link[1]['lat'])][strval($link[1]['lng'])][0];
		if (!array_key_exists('title', $link[0])) {
			$link[0]['title'] = $portalData[strval($link[0]['lat'])][strval($link[0]['lng'])][1];
			$link[1]['title'] = $portalData[strval($link[1]['lat'])][strval($link[1]['lng'])][1];
		}
		$row = [$linkNumber, $srcRidge, $link[0]['title'], $srcLnk, $dstRidge, $link[1]['title'], $dstLnk];
		fputcsv($handle, $row, ',', '"');
	}
	fputcsv($handle, ['', ''], ',', '"');
	$header = ['Портал', 'Ключей', 'SBUL'];
	fputcsv($handle, $header, ',', '"');
	foreach ($ridges as $ridge) {
		foreach ($ridge as $portal) {
			$sbulNeeded = intdiv($portal['linksOut'] - 1, 8);
			$row = [$portal['title'], $portal['keys'], $sbulNeeded];
			fputcsv($handle, $row, ',', '"');
		}
	}
	fclose($handle);
}