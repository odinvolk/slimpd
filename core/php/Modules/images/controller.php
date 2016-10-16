<?php
/* Copyright (C) 2016 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
$weightConf = trimExplode("\n", $app->config['images']['weightening'], TRUE);
$imageWeightOrderBy = "FIELD(pictureType, '" . join("','", $weightConf) . "'), sorting ASC, filesize DESC";

#echo $imageWeightOrderBy; die();
// predefined album-image sizes
foreach (array(35, 50,100,300,1000) as $imagesize) {
	$app->get('/image-'.$imagesize.'/album/:itemUid', function($itemUid) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$image = \Slimpd\Models\Bitmap::getInstanceByAttributes(
			array('albumUid' => $itemUid), $imageWeightOrderBy
		);
		if($image !== NULL) {
			$image->dump($imagesize, $app);
			return;
		}
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'album']));
	});

	$app->get('/image-'.$imagesize.'/track/:itemUid', function($itemUid) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$image = \Slimpd\Models\Bitmap::getInstanceByAttributes(
			array('trackUid' => $itemUid), $imageWeightOrderBy
		);
		if($image !== NULL) {
			$image->dump($imagesize, $app);
			return;
		}
		$track = \Slimpd\Models\Track::getInstanceByAttributes(['uid' => $itemUid]);
		$app->response->redirect($app->config['root'] . 'image-'.$imagesize.'/album/' . $track->getAlbumUid());
	});

	$app->get('/image-'.$imagesize.'/id/:itemUid', function($itemUid) use ($app, $vars, $imagesize, $imageWeightOrderBy){
		$image = \Slimpd\Models\Bitmap::getInstanceByAttributes(
			array('uid' => $itemUid), $imageWeightOrderBy
		);
		if($image !== NULL) {
			$image->dump($imagesize, $app);
			return;
		}
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
	});

	$app->get('/image-'.$imagesize.'/path/:itemParams+', function($itemParams) use ($app, $vars, $imagesize){
		$image = new \Slimpd\Models\Bitmap();
		$image->setRelPath(join(DS, $itemParams))->dump($imagesize, $app);
	})->name('imagepath-' .$imagesize);
	
	$app->get('/image-'.$imagesize.'/searchfor/:itemParams+', function($itemParams) use ($app, $vars, $imagesize){
		$filesystemReader = new \Slimpd\Modules\importer\FilesystemReader();
		$images = $filesystemReader->getFilesystemImagesForMusicFile(join(DS, $itemParams));
		
		if(count($images) === 0) {
			$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
			return;
		}
		// pick a random image
		shuffle($images);
		$path = array_shift($images);
		
		$app->response->redirect($app->urlFor('imagepath-'.$imagesize, ['itemParams' => path2url($path)]));
	});
	
	$app->get('/imagefallback-'.$imagesize.'/:type', function($type) use ($app, $vars, $imagesize){
		$vars['imagesize'] = $imagesize;
		$vars['color'] = $vars['images']['noimage'][ $vars['playerMode'] ]['color'];
		$vars['backgroundcolor'] = $vars['images']['noimage'][ $vars['playerMode'] ]['backgroundcolor'];
		
		switch($type) {
			case 'artist': $template = 'svg/icon-artist.svg'; break;
			case 'genre': $template = 'svg/icon-genre.svg'; break;
			case 'noresults': $template = 'svg/icon-noresults.svg'; break;
			case 'broken': $template = 'svg/icon-broken-image.svg'; break;
			default: $template = 'svg/icon-album.svg';
		}
		$app->response->headers->set('Content-Type', 'image/svg+xml');
		header("Content-Type: image/svg+xml");
		$app->render($template, $vars);
	})->name('imagefallback-' .$imagesize);
	
	// missing track or album paramter caused by items that are not imported in slimpd yet
	# TODO: maybe use another fallback image for those items...
	$app->get('/image-'.$imagesize.'/album/', function() use ($app, $vars, $imagesize){
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'album']));
	});
	$app->get('/image-'.$imagesize.'/track/', function() use ($app, $vars, $imagesize){
		$app->response->redirect($app->urlFor('imagefallback-'.$imagesize, ['type' => 'track']));
	});
}