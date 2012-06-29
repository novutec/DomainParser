<?php
/**
 * Novutec Domain Tools
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category   Novutec
 * @package    DomainParser
 * @copyright  Copyright (c) 2007 - 2012 Novutec Inc. (http://www.novutec.com)
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 */

/**
 * @namespace Novutec\DomainParser
 */
namespace Novutec\DomainParser;

/**
 * @see IdnaConverter
 */
require_once 'DomainParser/Idna.php';

/**
 * @see DomainParserResult
 */
require_once 'DomainParser/Result.php';

/**
 * @see DomainParserException
 */
require_once 'DomainParser/Exception.php';

/**
 * DomainParser
 *
 * @category   Novutec
 * @package    DomainParser
 * @copyright  Copyright (c) 2007 - 2012 Novutec Inc. (http://www.novutec.com)
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 * @todo       adding json support
 */
class Parser
{

    /**
     * Is the top-level domain list already be loaded?
     * 
     * @var boolean
     * @access protected;
     */
    protected $loaded = false;

    /**
     * Should the exceptions be thrown or caugth and trapped in the response?
     * 
     * @var boolean
     * @access protected
     */
    protected $throwExceptions = false;

    /**
     * Should the cache file always be loaded from the server?
     * 
     * @var boolean
     * @access protected
     */
    protected $reload = false;

    /**
     * Life time of cached file
     * 
     * @var integer
     * @access protected
     */
    protected $cacheTime = 432000;

    /**
     * List of all top-level domain names
     *
     * @var array
     * @access protected
     */
    protected $tldList = array();

    /**
     * URL to top-level domain name list
     *
     * @var string
     * @access protected
     */
    protected $tldUrl = 'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1';

    /**
     * Output format 'object', 'array' or 'json'
     *
     * @var string
     * @access protected
     */
    protected $format = 'object';

    /**
     * Creates a DomainParser object
     *
     * @param  boolean $throwException
     * @param  integer $cacheTime
     * @return void
     */
    public function __construct($throwExceptions = false, $cacheTime = 432000)
    {
        $this->throwExceptions($throwExceptions);
        $this->cacheTime($cacheTime);
    }

    /**
     * Tries to parse a string and to get the domain name, tld and idn
     * converted domain name.
     * 
     * If given string is not a domain name, it will add a default tld.
     * 
     * Also skips given string if it is longer than 63 characters.
     *
     * @param  string $unparsedString        
     * @param  string $defaultTld        
     * @return void
     */
    public function parse($unparsedString, $defaultTld = 'com')
    {
        try {
            if ($this->loaded === false) {
                $this->load();
            }
            
            $matchedDomain = '';
            $matchedDomainIdn = '';
            $matchedTld = '';
            $matchedTldIdn = '';
            
            $IdnaConverter = new Idna(array('idn_version' => 2008));
            
            preg_match('/^((http|https|ftp|ftps|news|ssh|sftp|gopher):[\/]{2,})?([^\/]+)/', mb_strtolower(trim($unparsedString), 'UTF-8'), $matches);
            $parsedString = end($matches);
            
            foreach ($this->tldList['content'] as $tld) {
                if (preg_match('/\.' . $tld . '$/', $parsedString, $trash)) {
                    $matchedTld = $tld;
                    $matchedTldIdn = $IdnaConverter->encode($tld);
                    $matchedDomain = str_replace('.' . $matchedTld, '', $parsedString);
                    
                    if (strpos($matchedDomain, '.')) {
                        $matchedDomain = str_replace('.', '', strrchr($matchedDomain, '.'));
                    }
                    
                    $matchedDomainIdn = $IdnaConverter->encode($matchedDomain);
                    
                    break;
                }
            }
            
            if ($matchedDomain == '' && strlen($IdnaConverter->encode($parsedString)) <= 63) {
                $matchedDomain = $IdnaConverter->decode(preg_replace('/[^a-zA-Z0-9\-]/', '', $IdnaConverter->encode($parsedString)));
                $matchedDomainIdn = $IdnaConverter->encode($matchedDomain);
                $matchedTld = $matchedTldIdn = $defaultTld;
            } elseif ($matchedDomain != '' && strlen($matchedDomainIdn) <= 63 && $matchedTld != '') {
                $matchedDomain = $IdnaConverter->decode(preg_replace('/[^a-zA-Z0-9\-]/', '', $IdnaConverter->encode($matchedDomain)));
                $matchedDomainIdn = $IdnaConverter->encode($matchedDomain);
            } else {
                throw new \Novutec\DomainParser\Exception('Unparsable domain name.');
            }
            
            $Result = new Result($matchedDomain, $matchedDomainIdn, $matchedTld, $matchedTldIdn);
            
            switch ($this->format) {
                case 'json':
                    return $Result->toJson();
                    break;
                case 'array':
                    return $Result->toArray();
                    break;
                default:
                    return $Result;
            }
        } catch (\Novutec\DomainParser\Exception $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            
            $result = new Result();
            $result->exception = $e;
            
            return $result;
        }
    }

    /**
     * Checks if the domain list exists or cached time is reached
     *
     * @throws DomainParserException
     * @return void
     */
    private function load()
    {
        $filename = sys_get_temp_dir() . '/domainparsertld.txt';
        
        if (file_exists($filename)) {
            $this->tldList = unserialize(file_get_contents($filename));
            if (time() - $this->tldList['timestamp'] > $this->cacheTime) {
                $reload = true;
            }
        }
        
        if (! file_exists($filename) || $this->reload === true) {
            $this->catchTlds();
            $file = fopen($filename, 'w+');
            
            if ($file === false) {
                throw new \Novutec\DomainParser\Exception('Could not open cache file.');
            }
            
            if (fwrite($file, serialize($this->tldList)) === false) {
                throw new \Novutec\DomainParser\Exception('Could not write to file.');
            }
            
            fclose($file);
        }
        
        $this->loaded = true;
    }

    /**
     * Catch list from server and parse them to array.
     *
     * It drops DynDNS names and adds manually third-level domain names
     * (e.g. co.uk, me.uk) because they are missing in the list.
     *
     * The manual added list is not complete.
     *
     * @throws DomainParserException
     * @return void
     */
    private function catchTlds()
    {
        $content = @file($this->tldUrl);
        
        if ($content === false) {
            throw new \Novutec\DomainParser\Exception('Could not catch file from server.');
            return;
        }
        
        $subtlds = array();
        
        foreach ($content as $num => $line) {
            $line = trim($line);
            
            if ($line == '') {
                continue;
            }
            if (strstr($line, '// DynDNS.com')) {
                break;
            }
            if (@substr($line[0], 0, 2) == '/') {
                continue;
            }
            if (@$line[0] == '.') {
                $line = substr($line, 1);
            }
            if (@$line[0] == '*') {
                $line = substr($line, 2);
            }
            if (strstr($line, '!')) {
                continue;
            }
            $subtlds[] = $line;
        }
        
        $subtlds = array_merge(array('com.cc', 'org.cc', 'edu.cc', 'net.cc', 'co.uk', 'me.uk', 
                'net.uk', 'org.uk', 'sch.uk', 'ac.uk', 'gov.uk', 'nhs.uk', 'police.uk', 'mod.uk', 
                'asn.au', 'com.au', 'net.au', 'id.au', 'org.au', 'edu.au', 'gov.au', 'csiro.au', 
                'co.ke', 'or.ke', 'ne.ke', 'go.ke', 'ac.ke', 'sc.ke', 'me.ke', 'mobi.ke', 'info.ke'), $subtlds);
        $this->tldList['content'] = array_unique($subtlds);
        $this->tldList['timestamp'] = time();
        usort($this->tldList['content'], function ($a, $b)
        {
            return strlen($b) - strlen($a);
        });
    }

    /**
     * Set output format
     *
     * You may choose between 'object', 'array' or 'json' output format
     *
     * @param  string $format
     * @return void
     */
    public function setFormat($format = 'object')
    {
        $this->format = $format;
    }

    /**
     * Set the throwExceptions flag
     * 
     * Set whether exceptions encounted in the dispatch loop should be thrown
     * or caught and trapped in the response object.
     * 
     * Default behaviour is to trap them in the response object; call this
     * method to have them thrown.
     * 
     * @param  boolean $throwExceptions
     * @return void
     */
    public function throwExceptions($throwExceptions = false)
    {
        $this->throwExceptions = $throwExceptions;
    }

    /**
     * Set the reload flag
     * 
     * Set if the top-level domain list should be reloaded independet from
     * the cache time.
     * 
     * @param  boolean $reload
     * @return void
     */
    public function reload($reload = false)
    {
        $this->reload = $reload;
    }

    /**
     * Set the cache time
     * 
     * By default the cache time is 432000 (equal to 5 days)
     *
     * @param  integer $cacheTime
     * @return void
     */
    public function cacheTime($cacheTime = 432000)
    {
        $this->cacheTime = $cacheTime;
    }
}