<?php
include('stopwatch.php');

/**
 * A test class with a bunch of annotations
 * 
 * @author(steven@kotuha.be)
 * 
 * @Path("http://www.flickr.com/services/feeds/photos_public.gne?format=json")
 * @JSONP({callbackParam="jsonFlickrFeed"}) 
 * @POST @Email('steven@kotuha.be')
 * @Persistent(0.5)
 * @Cachable(false)
 * @deprecated 
 * @Table(["people",-0.3444, "wut?"])
 * @Secured({role = "admin", level = .156})
 */
class TestClass
{
	/** @Filters(["html", "tidy"]) */
	protected $_var1;
	/** @Filters(["json"]) */
	protected $_var2;
	/** @Filters(["url"]) */
	protected $_var3;
	/** @Filters(["raw"]) */
	protected $_var4;
}

$stopwatch = new StopWatch();
$stopwatch->start();

//for($i=0;$i<=1;$i++) 
//{
	$oClassReflect = new ReflectionClass("TestClass");
	/*foreach($oClassReflect->getProperties(ReflectionProperty::IS_PROTECTED) as $property)
	{
		$sDocComment = $property->getDocComment();
	
		$parser = new Parser();
		$annotations = $parser->parse($sDocComment);

		var_dump($property->getName(), $sDocComment, $annotations);
	}*/
	$sDocComment = $oClassReflect->getDocComment();
	$parser = new Parser();
	$annotations = $parser->parse($sDocComment);
//}

$stopwatch->stop();
echo 'Parsed in : '. $stopwatch->get(true);

echo '<pre>'.$sDocComment.'</pre>';
var_dump($annotations);

class Parser
{
	const TOKEN_NONE = 0; 
	const TOKEN_PARENTHESIS_OPEN = 1;
	const TOKEN_PARENTHESIS_CLOSE = 2;
	const TOKEN_CURLY_OPEN = 3;
	const TOKEN_CURLY_CLOSE = 4;
	const TOKEN_SQUARED_OPEN = 5;
	const TOKEN_SQUARED_CLOSE = 6;
	const TOKEN_COMMA = 7;
	const TOKEN_STRING = 8;
	const TOKEN_NUMBER = 9;
	const TOKEN_AT_SIGN = 10;
	const TOKEN_TRUE = 11;
	const TOKEN_FALSE = 12;
	const TOKEN_NULL = 13;
	const TOKEN_LETTER = 14;
	const TOKEN_EQUALS = 15;
	
	protected $_annotations;
	protected $_excludes = array('author', 'copyright', 'license', 'see', 'link', 'since');
	
	public function getAnnotation($name = null)
	{
		if(!$name) {
			return $this->_annotations;
		}
		
		return isset($this->_annotations[$name]) ? $this->_annotations[$name] : null; 
	}
	
	public function parse($block)
	{
		$block = $this->_cleanUp($block);
		
		$charArray = str_split($block);
		$index = 0;
		
		$this->_parseBlock($charArray, $index);

		return $this->_annotations;
	}
	
	protected function _parseBlock(array $block, $index)
	{
		$this->_annotations = array();
		
		for(;$index<count($block); $index++)
		{
			$char = $block[$index];
			$prev = $index > 0 ? $block[$index-1] : ' ';
			$next = $index < count($block) - 1 ? $block[$index+1] : ' ';
			
			if($this->_getToken($char) == self::TOKEN_AT_SIGN 
				&& strpos(" \t\n\r", $prev) !== FALSE
				&& stripos("abcdefghijklmnopqrstuvwxyz", $next) !== FALSE)
			{		
					// extract the annotation name
					$name = $this->_parseAnnotationName($block, $index);

					if(in_array(strtolower($name), $this->_excludes)) {
						continue;
					}
					
					// get the annotation's value
					if(isset($block[$index]) && $this->_getToken($block[$index]) == self::TOKEN_PARENTHESIS_OPEN)
					{
						$index++; // skip the parenthesis
						$value = $this->_parseValue($block, $index);
					}
					else {
						$value = true; // flag it
					}
					
					$this->_annotations[$name] = $value;
			}
		}
		
		return $this->_annotations;
	} 
	
	protected function _parseAnnotationName(array $block, &$index)
	{
		$index++; // skip the @ sign
		
		return $this->_parseFieldName($block, $index);
	}
	
	protected function _parseFieldName(array $block, &$index)
	{
		$this->_eatWhitespace($block, $index);
		
		$name = '';
		
		for(;$index<count($block);$index++)
		{
			$char = $block[$index];

			if($this->_getToken($char) != self::TOKEN_LETTER)
			{
				return $name;
			}
			
			$name .= $char;
		}
		
		return $name;
	}

	protected function _parseValue(array $block, &$index)
	{		
		$c = $this->_lookAhead($block, $index);

		switch($c)
		{
			case self::TOKEN_STRING:
				return $this->_parseString($block, $index);
			case self::TOKEN_CURLY_OPEN:
				return $this->_parseObject($block, $index);
			case self::TOKEN_SQUARED_OPEN:
				return $this->_parseArray($block, $index);
			case self::TOKEN_NUMBER:
				return $this->_parseNumber($block, $index);
			case self::TOKEN_FALSE:
				return false;
			case self::TOKEN_TRUE:
				return true;
			case self::TOKEN_NULL:
				return null;
		}
		
		return null;
	}
	
	protected function _lookAhead(array $block, $index)
	{
		$saveIndex = $index;

		return $this->_nextToken($block, $saveIndex);
	}
	
	protected function _nextToken(array $block, &$index)
	{
		$this->_eatWhitespace($block, $index);

		if ($index == count($block)) {
			return self::TOKEN_NONE;
		}

		$remainingLength = count($block) - $index;

		// false
		if ($remainingLength >= 5) {
			if ($block[$index] == 'f' &&
				$block[$index + 1] == 'a' &&
				$block[$index + 2] == 'l' &&
				$block[$index + 3] == 's' &&
				$block[$index + 4] == 'e') {
				$index += 5;
				return self::TOKEN_FALSE;
			}
		}

		// true
		if ($remainingLength >= 4) {
			if ($block[$index] == 't' &&
				$block[$index + 1] == 'r' &&
				$block[$index + 2] == 'u' &&
				$block[$index + 3] == 'e') {
				$index += 4;
				return self::TOKEN_TRUE;
			}
		}

		// null
		if ($remainingLength >= 4) {
			if ($block[$index] == 'n' &&
				$block[$index + 1] == 'u' &&
				$block[$index + 2] == 'l' &&
				$block[$index + 3] == 'l') {
				$index += 4;
				return self::TOKEN_NULL;
			}
		}
		
		$c = $block[$index];
		$index++;

		$token = $this->_getToken($c);
		if($token != self::TOKEN_NONE) {
			return $token;
		}
		
		$index--;

		return self::TOKEN_NONE;
	}
	
	protected function _getToken($c)
	{
		switch(strtolower($c))
		{
			case '(':
				return self::TOKEN_PARENTHESIS_OPEN;
			case ')':
				return self::TOKEN_PARENTHESIS_CLOSE;
			case '[':
				return self::TOKEN_SQUARED_OPEN;
			case ']':
				return self::TOKEN_SQUARED_CLOSE;
			case '{':
				return self::TOKEN_CURLY_OPEN;
			case '}':
				return self::TOKEN_CURLY_CLOSE;
			case ',':
				return self::TOKEN_COMMA;
			case 'a': case 'b': case 'c': case 'd': case 'e': 
			case 'f': case 'g': case 'h': case 'i': case 'j': 
			case 'k': case 'l': case 'm': case 'n': case 'o': 
			case 'p': case 'q': case 'r': case 's': case 't': 
			case 'u': case 'v': case 'w': case 'x': case 'y': 
			case 'z': 
				return self::TOKEN_LETTER;
			case '"': case '\'':
				return self::TOKEN_STRING;
			case '@':
				return self::TOKEN_AT_SIGN;
			case '=':
				return self::TOKEN_EQUALS;
			case '0': case '1': case '2': case '3': case '4': 
			case '5': case '6': case '7': case '8': case '9':
			case '-': case '.':
				return self::TOKEN_NUMBER;
		}
		
		return self::TOKEN_NONE;
	}
	
	protected function _parseObject(array $block, &$index)
	{
		// {
		$this->_nextToken($block, $index);
		
		$this->_eatWhitespace($block, $index);
		$object = new stdClass;
		
		$done = false;
		while (!$done)
		{
			$token = $this->_lookAhead($block, $index);
			
			if ($token == self::TOKEN_NONE) {
				return null;
			} else if($token == self::TOKEN_COMMA) {
				$this->_nextToken($block, $index);
			}
			else if($token == self::TOKEN_CURLY_CLOSE)
			{
				$this->_nextToken($block, $index);
				return $object;
			}
			else
			{
				// name
				$name = $this->_parseFieldName($block, $index);
				
				// =
				$token = $this->_nextToken($block, $index);
				if($token != self::TOKEN_EQUALS) {
					return null;
				}

				// value
				$value = $this->_parseValue($block, $index);
				
				$object->{$name} = $value;
			}
		}
		
		return $object;
	}
	
	protected function _parseArray(array $block, &$index)
	{
		$array = array();

		// [
		$this->_nextToken($block, $index);

		$done = false;
		while(!$done)
		{
			$token = $this->_lookAhead($block, $index);
			
			if ($token == self::TOKEN_NONE) {
				return null;
			} else if ($token == self::TOKEN_COMMA) {
				$this->_nextToken($block, $index);
			} else if ($token == self::TOKEN_SQUARED_CLOSE) {
				$this->_nextToken($block, $index);
				break;
			}
			else
			{
				$value = $this->_parseValue($block, $index);

				$array[] = $value;
			}
		}

		return $array;
	}
	
	protected function _parseNumber(array $block, &$index)
	{
		$this->_eatWhitespace($block, $index);

		$lastIndex = $this->_getLastIndexOfNumber($block, $index);
		$charLength = ($lastIndex - $index) + 1;

		$slice = array_slice($block, $index, $charLength);
		$str = implode('', $slice);
		$success = is_numeric($str);
		$number = null;
		
		if(!$success) {
			return null;
		}
		
		$number = (float) $str;
		$index = $lastIndex + 1;
		
		return $number;
	}
	
	protected function _parseString(array $block, &$index)
	{
		$this->_eatWhitespace($block, $index);
		$string = "";

		// " or '
		$c = $block[$index++];
		$close = $c;

		$complete = false;
		while(!$complete)
		{
			if($index == count($block)) {
				break;
			}

			$c = $block[$index++];

			if($c == $close) 
			{
				$complete = true;
				break;
			}
			else if($c == "\\")
			{
				if($index == count($block)){
					break;
				}

				$c = $block[$index++];
				if($c == '"') {
					$string .= '"';
				} else if($c == '\\') {
					$string .= "\\";
				} else if($c == '/') {
					$string .= "/";
				} else if($c == 'b') {
					$string .= "\b";
				} else if($c == 'f') {
					$string .= "\f";
				} else if($c == 'n') {
					$string .= "\n";
				} else if($c == 'r') {
					$string .= "\r";
				} else if($c == 't') {
					$string .= "\t";
				} else if($c == 'u') {
					// @TODO : find out how to convert unicode code points to chars
					$remainingLength = count($block) - $index;
					if ($remainingLength >= 4) {
						$val = $block[$index].$block[$index+1].$block[$index+2].$block[$index+3];
						$arr = array_slice($block, $index, 4);
						$hex = implode('', $arr);
						$dec = hexdec($hex);
						$entity = '&#'.$dec.';';
						$char = html_entity_decode($entity, ENT_COMPAT, 'UTF-8');
						$string .= $char;

						$index += 4;
					} else {
						break;
					}
				}
			} else {
				$string .= $c;
			}

		}

		if (!$complete) {
			return null;
		}

		return $string;
	}
	
	protected function _eatWhitespace(array $block, &$index)
	{
		for($i=0; $index < count($block); $index++) {
			if( strpos(" \t\n\r", $block[$index]) === FALSE ) {
				return;
			}
		}
	}
	
	protected function _getLastIndexOfNumber(array $block, &$index)
	{
		$lastIndex = null;

		for($lastIndex = $index; $lastIndex < count($block); $lastIndex++) {
			if ( strpos("0123456789+-.eE", $block[$lastIndex]) === false) {
				break;
			}
		}

		return $lastIndex - 1;
	}
	
	protected function _cleanUp($block)
	{
		$block = preg_replace("/(^[\\s]*\\/\\*\\*)
                                 |(^[\\s]\\*\\/)
                                 |(^[\\s]*\\*?\\s)
                                 |(^[\\s]*)
                                 |(^[\\t]*)/ixm", "", $block);
		$block = trim($block);

    	$block = str_replace("\r", "", $block);
		$block = preg_replace("/([\\t])+/", "\t", $block);

		return $block;
	}
}
