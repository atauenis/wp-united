<?php
/** 
*
* WP-United "CSS Magic" template integrator
*
* @package WP-United
* @version $Id: 0.9.1.5  2012/12/28 John Wells (Jhong) Exp $
* @copyright (c) 2006-2013 wp-united.com
* @license http://opensource.org/licenses/gpl-license.php GNU Public License  
* @author John Wells
*
*/

/**
 *	You will want to remove this line in your own projects.
*/
if ( !defined('IN_PHPBB') && !defined('ABSPATH') ) exit;

/**
 * This library attempts to magically fuse templates on the page
 * by modifying CSS specicifity on the fly
 * Inspired by cssParser class. you are welcome to use this class in your own projects, provided this licence is kept intact.
 * This class should be useful for a wide range of template integration projects.
 * @package CSS Magic
 * @author John Wells
 *
 * USAGE NOTES:
 * 
 * instantiate CSS Magic:
 * $cssMagic = CSS_Magic::getInstance();
 * 
 * To read a css file:
 * $success = $cssMagic->parseFile('path_to_file');
 * 
 * Or read a css string:
 * $cssMagic->clear(); // you can read multiple strings and files. They are combined together. To start anew, use this.
 * $success = $cssMagic->parseString($cssString);
 * $success will return 1 or true on success, or 0 or false on failure.
 * 
 * Now, make all the CSS we have read in apply only to children of a particular DIV with ID = $id
 * $cssMagic->makeSpecificById($id);
 * 
 * Or, we could use $cssMagic->makeSpecificByClass($class) 
 * Or, both :-) $cssMagic->makeSpecificByIdThenClass($classAndId) 
 * 
 * Now get the modified CSS. The output is fairly nicely compressed too.
 * $fixedCSS = $cssMagic->getCSS();
 * 
 * Alternatively, send the output straight to the browser as a CSS file:
 * echo $cssMagic; 
 * 
 * When you're finished,
 * $cssMagic->clear();
 * 
 * Note: CSS Magic doesn't try to validate the CSS coming in. If the inbound CSS in invalid or garbage, 
 * you'll get garbage coming out -- perhaps even worse than before.
 * 
 * (c) John Wells, 2009-2013
 */
class CSS_Magic {
	private 	$css,
				$filename,
				$nestedItems,
				$importedItems,
				$totalItems;
	
	/**
	 * If you want to use this class as a sngleton, invoke via CSS_Magic::getInstance();
	 */
	public static function getInstance () {
		static $instance;
		if (!isset($instance)) {
			$instance = new CSS_Magic();
        } 
        return $instance;
    }
	
	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->clear();
		$this->filename = '';
		$this->nestedItems = array();
		$this->importedItems = array();
		$this->totalItems = 0;
	}
	/**
	 * initialise or clear out internal representation
	 * @return void
	 */
	public function clear() {
		$this->css = array();
		$this->nestedItems = array();
		$this->importedItems = array();
	}

	/**
	 * Parses inbound CSS, storing it as an internal representation of keys and code
	 * @param string $str A valid CSS string
	 * @return int the number of CSS keys stored
	 */
	public function parseString($str) {
		$keys = '';
		
		$Instr = $str;
		// Remove comments
		$str = preg_replace("/\/\*(.*)?\*\//Usi", "", $str);
		$str = str_replace("\t", "", $str);
		$str = str_replace("}\\", '[TANTEK]', $str);
		// find nested stylesheets
		preg_match_all('/(\@[^\{]*\{)([^\{^\}]*(\{[^\@^\{^\}]*\}[^\{^\}]*)*?)\}/', $str, $nested);
		
		$nestIndex = sizeof($this->nestedItems);
		if(sizeof($nested[0]) && isset($nested[1]) && is_array($nested[1]) && sizeof($nested[1])) {
			foreach($nested[1] as $nestNum => $nestSel) {
				if(!empty($nestSel) && isset($nested[2]) && is_array($nested[2]) && isset($nested[2][$nestNum])) {
					// handle imported stylesheets separately
					if(stristr($nestSel, '@import') !== false) {
						continue;
					}

					$subSheet = new CSS_Magic();
					$this->totalItems = $this->totalItems + $subSheet->parseString($nested[2][$nestNum]);
				
					$this->nestedItems[$nestIndex] = array(
						'selector'	=> $nestSel,
						'content'	=> $subSheet
					);
					
					$str = str_replace($nested[0][$nestNum], '[WPU_NESTED] {' . $nestIndex . '}', $str);
					$nestIndex++;
				}
			}
		}
		
		// Other nested stylesheets:
		preg_match_all('/\@import\s(url\()?[\'"]?([^\'^"^\)]*)[\'"]?\)?;/', $str, $imported);
		$importIndex = sizeof($this->importedItems);
		if(sizeof($imported[0]) && isset($imported[2]) && is_array($imported[2]) && sizeof($imported[2])) {
			foreach($imported[2] as $importNum => $importUrl) {
			
				// TODO: Filename is $imported[2]
			
				$this->totalItems = $this->totalItems + 1;
				$this->importedItems[$importIndex] = $imported[0][$importNum];
				
				$str = str_replace($imported[0][$importNum], '[WPU_NESTED_IMPORT] {' $importIndex . '}', $str);
				$importIndex++;
			}
		}

		
		$parts = explode("}",$str);

		if(count($parts) > 0) {
			foreach($parts as $part) { 
				if(strpos($part, '{') !== FALSE) {
					list($keys,$cssCode) = explode('{', $part);
					// store full selector
					if(strlen($keys) > 0) {
						$keys = str_replace("\n", "", $keys);
						$keys = str_replace("\r", "", $keys);
						$keys = str_replace("\\", "", $keys);
						$this->addSelector($keys, trim($cssCode));
					}
				}
			}
		}
		
		// process nested stylesheets too
		$this->totalItems = $this->totalItems + count($this->css);
		return ($this->totalItems);
	}
	/**
	 * Opens and parses a CSS file
	 * @param string $filename The path and name of the file 
	 * @param bool $clear Set to true to clear out the internal representation and start again. Leave false to add to what we already have.
	 * @return void
	 */
	public function parseFile($filename, $clear = false) {
		if ($clear) $this->clear();
		$this->filename = $filename;
		if(@file_exists($filename)) {
			return $this->parseString(file_get_contents($filename), $clear);
		} else {
			return false;
		}
	}
	/**
	 * Add selector (private) -- adds a selector to the internal representation
	 * @return void
	 */
	private function addSelector($keys, $cssCode) {
		$keys = trim($keys);
		$cssCode = trim($cssCode);
		
		while(array_key_exists($keys, $this->css)) {
			$keys = "__ " . $keys;
		}
		
			$this->css[$keys] = $cssCode;

	}
	/**
	 * Makes the CSS more specific by applying an outer ID
	 * @param string $id The DOM ID to use
	 * @param bool $removeBody Whether the body tag should be ignored
	 * @return void
	 */
	public function makeSpecificById($id, $removeBody = false) {
		$this->_makeSpecific("#{$id}", $removeBody);
	}
	/**
	 * Makes the CSS more specific by applying an outer class name
	 * @param string $class The document class to use
	 * @param bool $removeBody Whether the body tag should be ignored
	 * @return void
	 */
	public function makeSpecificByClass($class, $removeBody = false) {
		$this->_makeSpecific(".{$class}", $removeBody);
	}
	/**
	 * Makes the CSS more specific by applying an outer ID and class
	 * @param string $classAndIdThe string to prepend
	 * @param bool $removeBody Whether the body tag should be ignored
	 * @return void
	 */
	public function makeSpecificByIdThenClass($classAndId, $removeBody = false) {
		$this->_makeSpecific("#{$classAndId} .{$classAndId}", $removeBody);
	}
	/**
	 * Applies a prefix (e.g. "wpu") to specific IDs
	 * @param string prefix the prefix to apply
	 * @param bool $IDs an array of IDs to modify
	 * @return void
	 */
	public function renameIds($prefix, $IDs) {
		$fixed = array();
		$searchStrings = array();
		$replStrings = array();
		if(sizeof($IDs)) {
			foreach($IDs as $ID) {
				foreach(array(' ', '{', '.', '#', ':') as $suffix) {
					$searchStrings[] = "#{$ID}{$suffix}";
					$replStrings[] = "#{$prefix}{$ID}{$suffix}";
				}
			}
			foreach($this->css as $keyString => $cssCode) {
				$fixed[str_replace($searchStrings, $replStrings, $keyString)] = $cssCode;
			}
			$this->css = $fixed;
		}
		unset($fixed);
	}
	/**
	 * Applies a prefix (e.g. "wpu") to specific classes
	 * @param string prefix the prefix to apply
	 * @param bool $classess an array ofclasses to modify
	 * @return void
	 */	
	public function renameClasses($prefix, $classes) {
		$fixed = array();
		$searchStrings = array();
		$replStrings = array();
		if(sizeof($classes)) {
			foreach($classes as $class) {
				foreach(array(' ', '{', '.', '#', ':') as $suffix) {
					$searchStrings[] = '#' . $class . $suffix;
					$replStrings[] = "#{$prefix}{$class}";
				}
			}
			foreach($this->css as $keyString => $cssCode) {
				$fixed[str_replace($searchStrings, $replStrings, $keyString)] = $cssCode;
			}
			$this->css = $fixed;
		}		
		unset($fixed);
	}	
	
	/**
	 * Makes all stored CSS specific to a particular parent ID or class
	 * @param string prefix the prefix to apply
	 * @param bool  $removeBody: set to true to ignore body keys
	 * @access private but marked as public as we can have recursive (nested) CSS Magics.
	 * @return void
	 */
	public function _makeSpecific($prefix, $removeBody = false) {
		$fixed = array();
		// things that could be delimiting a "body" selector at the beginning of our string.
		$seps = array(' ', '>', '<', '.', '#', ':', '+', '*', '[', ']', '?');
		$index = 0;
		foreach($this->css as $keyString => $cssCode) {
			$keyString = str_replace('__ ', '', $keyString);
			$index++;
			$fixedKeys = array();
			if($keyString ==  '[WPU_NESTED]') {
				$fixedKeys = array('[WPU_NESTED]');
				$nestedCodeKey = (int)$cssCode;
				$this->nestedItems[$nestedCodeKey]['content']->_makeSpecific($prefix, $removeBody);
			} else if($keyString == '[WPU_NESTED_IMPORT]') {
				// TODO: process nested import
			
			} else {
				$keys = explode(',', $keyString);
				foreach($keys as $key) {
					$fixedKey = trim($key);
					$foundBody = false;
					// remove references to 'body'
					//$keyElements = preg_split('/[\s<>\.#\:\+\*\[\]\?]/');
					foreach($seps as $sep) {
						$keyElements = explode($sep, $fixedKey);
						$bodyPos = array_search("body", $keyElements);
						if($bodyPos !== false) {
							$keyElements[$bodyPos] = $prefix;
							if(!$removeBody) {
								if(sizeof($keyElements) > 1) { 
									$fixedKey = implode($sep, $keyElements);
								} else {
									$fixedKey = $keyElements[$bodyPos]; 
								}
							
							} 
							$foundBody = true;
						}
					}
					// add prefix selector before each selector
					if(!$foundBody) {
						if(($fixedKey[0] != "@") && (strlen(trim($fixedKey)))) {
							if(strpos($fixedKey, '* html') !== false) { // ie hack
								$fixedKey = str_replace('* html', '* html ' . $prefix . ' ', $fixedKey);
							} elseif(strpos($fixedKey, '*+ html') !== false) { // ie7 hack
								$fixedKey = str_replace('*+ html', '*+ html ' . $prefix . ' ', $fixedKey);
							} elseif($fixedKey == 'html') {
								$fixedKey = $prefix;
							} else {
								$fixedKey = "{$prefix} " . $fixedKey;
							}
							
						}
					
					}
					if(!empty($fixedKey)) {
						$fixedKeys[] = $fixedKey;
					}
				}
				
			} 
			
			// recreate the fixed key
			if(sizeof($fixedKeys)) {
				$fixedKeyString = implode(', ', $fixedKeys);
			
				while(array_key_exists($fixedKeyString, $fixed)) {
					$fixedKeyString = "__ " . $fixedKeyString;
				}	
				$fixed[$fixedKeyString] = $cssCode;

			}
		}

		// done
		$this->css = $fixed;
		unset($fixed);

	}
	
	/**
	 * Removes common elements from CSS selectors
	 * For example, this can be used to undo CSS magic additions
	 * @param string $txt the stuff to wipe out
	 * @return void
	 */
	public function removeCommonKeyEl($txt) {
		$newCSS = array();
		foreach($this->css as $keyString => $cssCode) {
			$newKey = trim(str_replace($txt, '', $keyString));
			if(!empty($newKey)) {
				$newCSS[$newKey] = $cssCode;
			}
		}
		$this->css = $newCSS;
		unset($newCSS);
		
		foreach($this->nestedItems as $index => $nestedItem) {
			$nestedItem['content']->removeCommonKeyEl($txt);
		}
	}
	
	/**
	 * Returns all key classes and IDs
	 * @return array an array with all classes and IDs
	 */
	public function getKeyClassesAndIDs() {
		$classes = array();
		$ids = array();
		foreach($this->css as $keyString => $cssCode) {
			$keyString = str_replace('__ ', '', $keyString);
			preg_match_all('/\..[^\s^#^>^<^\.^,^:]*/', $keyString, $cls);
			preg_match_all('/#.[^\s^#^>^<^\.^,^:]*/', $keyString, $id);
			
			if(sizeof($cls[0])) {
				$classes = array_merge($classes, $cls[0]);
			}
			if(sizeof($id[0])) {
				$ids = array_merge($ids, $id[0]);
			}			
		}
		
		
		foreach($this->nestedItems as $index => $nestedItem) {
			$nestedEls = $nestedItem['content']->getKeyClassesAndIDs();
			if(sizeof($nestedEls['classes'])) {
				$classes = array_merge($classes, $nestedEls['classes']);
			}
			if(sizeof($nestedEls['ids'])) {
				$ids = array_merge($ids, $nestedEls['ids']);
			}
		}
		
		if(sizeof($classes)) {
			$classes = array_unique($classes);
		}
		if(sizeof($ids)) {
			$ids = array_unique($ids);
		}		
		return array('ids' => $ids, 'classes' => $classes);
	}
	
	
	/**
	 * Searchs through all keys and and makes modifications
	 * @param array $finds key elements to find
	 * @param array $replacements Matching replacements for key elements
	 * @return void
	 */
	public function modifyKeys($finds, $replacements) {
		$theFinds = array();
		$theRepl = array();
		// First prepare the find/replace strings
		foreach($finds as $findString) {
			$theFinds[] = '/' . str_replace('.', '\.', $findString) . '([\s#\.<>:]|$)/';
		}
		foreach($replacements as $replString) {
			$theRepl[] = $replString . '\\1';
		}

		$keys = array_keys($this->css);
		$values = array_values($this->css);
		
		$keys = preg_replace($theFinds, $theRepl, $keys);
		$this->css = array_combine($keys, $values);
		
	}
	

	/*
	 * returns all our stored, fixed (hopefully!) CSS
	 * @return string fixed CSS
	 */
	public function getCSS() {
		$response = '';
		foreach($this->css as $keyString => $cssCode) {
			$keyString = str_replace('__ ', '', $keyString);
			$cssCode = str_replace('[TANTEK]', "}\\", $cssCode);
			if($keyString == '[WPU_NESTED]') {
				$response .= $this->nestedItems[(int)$cssCode]['selector'];
				$response .= $this->nestedItems[(int)$cssCode]['content']->getCSS() . "}\n\n";;
			} elseif($keyString == '[WPU_NESTED_IMPORT]') {
				$response .= $this->importedItems[(int)$cssCode];
			}
				$response .= $keyString . '{' . $cssCode . "}\n\n";
			}
		}
		return $response;
	}
	/**
	 * Sends CSS directly to browser as text/css
	 * @return void
	 */
	public function sendCSS() {
		header("Content-type: text/css");
		echo $this->getCSS();
	
	}

	/**
	 * You can do echo $cssMagic and voila: A stylesheet is in the intertubes.
	 * @return CSS string
	 */
	public function __toString() {
		header("Content-type: text/css");
		$this->getCSS();
	}
}

// The end.
