<?php
/** Helpers */
class Stopwatch
{
	static $start;
	static $end;
	static $delta;
	
	public static function start()
	{
		self::$start = microtime(true);
	}
	
	public static function stop()
	{
		self::$end = microtime(true);
		self::$delta = self::$end - self::$start;
	}
	
	public static function get($readable = false)
	{
		if(!$readable) {
			return self::$delta;
		}
		
		if(self::$delta > 60)
		{
			$minutes = floor(self::$delta/60);
			$seconds = round((self::$delta - ($minutes*60)), 4);
			$time = $minutes.' minutes '.$seconds.' seconds';
		} else {
			$time = round(self::$delta, 4). ' seconds';
		}
		
		return $time;
	}
}