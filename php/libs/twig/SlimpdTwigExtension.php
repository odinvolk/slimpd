<?php
class Slimpd_Twig_Extension extends Twig_Extension implements Twig_ExtensionInterface
{
	/**
     * Name of this extension.
     *
     * @return string
     */
    public function getName()
    {
        return 'Slimpd';
    }
	
	public function getFunctions()
    {
        return array(
			new Twig_SimpleFunction('getRandomInstance', array($this, 'getRandomInstance'))
        );
    }
	
    public function getRandomInstance($type)
    {
        $classPath = '\\Slimpd\\' . $type;
		if(class_exists($classPath) === FALSE) {
			return NULL;
		}
        return $classPath::getRandomInstance();
    }
	
	public function getFilters()
    {
        return array(
			new \Twig_SimpleFilter('formatMiliseconds', function ($miliseconds) {
				return gmdate(($miliseconds > 3600000) ? "G:i:s" : "i:s", ($miliseconds/1000));
			}),
			
			new \Twig_SimpleFilter('path2url', function ($mixed) {
				if(is_array($mixed) === TRUE) {
					$mixed = join("", $mixed);
				}
				// rawurlencode but preserve slashes
				return str_replace('%2F', '/', rawurlencode($mixed));
			}),
			
			new \Twig_SimpleFilter('formatSeconds', function ($seconds) {
				$format = "G:i:s";
				$suffix = "h";
				if($seconds < 3600) {
					$format = "i:s";
					$suffix = "min";
				}
				if($seconds < 60) {
					$format = "s";
					$suffix = "sec";
				}
				if($seconds < 1) {
					return(round($seconds*1000)) . ' ms';
				}
				// remove leading zero
				return ltrim(gmdate($format, $seconds) . ' ' . $suffix, 0);
			}),
			
			new \Twig_SimpleFilter('shorty', function ($number) {
				if($number < 990) {
					return $number;
				}
				if($number < 990000) {
					return number_format($number/1000,0) . " K";
				}
				return number_format($number/1000000,1) . " M";
			}),
			
			new \Twig_SimpleFilter('formatBytes', function ($bytes, $precision = 2) {
				$units = array('B', 'KB', 'MB', 'GB', 'TB'); 
			    $bytes = max($bytes, 0); 
			    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
			    $pow = min($pow, count($units) - 1);
				$bytes /= pow(1024, $pow);
				return round($bytes, $precision) . ' ' . $units[$pow];
			}),
			
			new \Twig_SimpleFilter('shortenChecksum', function ($hash, $precision = 3) {
				return substr($hash,0,$precision) . '...' . substr($hash,$precision*-1);
			}),
			
			new \Twig_SimpleFilter('ll', function ($hans = array(), $vars = array()) {
				return \Slim\Slim::getInstance()->ll->str($hans, $vars);
			})
        );
    }

    public function getTests()
    {
        return array(
            new \Twig_SimpleTest('instanceofAlbum', function ($item) {
				return $item instanceof \Slimpd\Album;
			}),
			
			new \Twig_SimpleTest('instanceofTrack', function ($item) {
				return $item instanceof \Slimpd\Track;
			}),
			
			new \Twig_SimpleTest('instanceofLabel', function ($item) {
				return $item instanceof \Slimpd\Label;
			}),
			
			new \Twig_SimpleTest('instanceofGenre', function ($item) {
				return $item instanceof \Slimpd\Genre;
			}),
			
			new \Twig_SimpleTest('instanceofArtist', function ($item) {
				return $item instanceof \Slimpd\Artist;
			}),
			
			new \Twig_SimpleTest('instanceofDirectory', function ($item) {
				return $item instanceof \Slimpd\_Directory;
			})
        );
    }
}