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
 * define DomainParser Path
 */
define('DOMAINPARSERPATH', dirname(__FILE__));

/**
 * @see IdnaConverter
 */
require_once DOMAINPARSERPATH . '/Idna.php';

/**
 * @see DomainParserResult
 */
require_once DOMAINPARSERPATH . '/Result.php';

/**
 * @see DomainParserException
 */
require_once DOMAINPARSERPATH . '/Exception/AbstractException.php';

/**
 * DomainParser
 *
 * @category   Novutec
 * @package    DomainParser
 * @copyright  Copyright (c) 2007 - 2012 Novutec Inc. (http://www.novutec.com)
 * @license    http://www.apache.org/licenses/LICENSE-2.0
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
     * Output format 'object', 'array', 'json', 'serialize' or 'xml'
     *
     * @var string
     * @access protected
     */
    protected $format = 'object';

    /**
     * Encoding of domain name
     * 
     * @var string
     * @access protected
     */
    protected $encoding = 'utf-8';

    /**
     * Set cache path
     * 
     * @var string
     * @access protected
     */
    protected $path;

    /**
     * Creates a DomainParser object
     *
     * @param  string $format
     * @return void
     */
    public function __construct($format = 'object')
    {
        $this->setFormat($format);
        $this->setCachePath();
    }

    /**
     * Set cache path
     * 
     * @param  string $path
     * @return void
     */
    public function setCachePath($path = null)
    {
        if (is_null($path)) {
            $this->path = sys_get_temp_dir();
        } else {
            $this->path = filter_var($path, FILTER_SANITIZE_STRING);
        }
    }

    /**
     * Checks if given domain name is valid
     * 
     * @param  string $domain
     * @return boolean
     */
    public function isValid($domain)
    {
        $this->setFormat('object');
        $Result = $this->parse($domain, '');
        
        return $Result->validHostname;
    }

    /**
     * Tries to parse a string and to get the domain name, tld and idn
     * converted domain name.
     * 
     * If given string is not a domain name, it will add a default tld.
     * 
     * Also skips given string if it is longer than 63 characters.
     *
     * @throws instance of AbstractException if throwExceptions = true
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
            $validHostname = true;
            
            $IdnaConverter = new Idna(array('idn_version' => 2008));
            
            preg_match('/^((http|https|ftp|ftps|news|ssh|sftp|gopher):[\/]{2,})?([^\/]+)/', mb_strtolower(trim($unparsedString), $this->encoding), $matches);
            $parsedString = end($matches);
            
            foreach ($this->tldList['content'] as $tld) {
                if (preg_match('/\.' . $tld . '$/', $parsedString, $trash)) {
                    $matchedTld = $tld;
                    $matchedTldIdn = $IdnaConverter->encode($tld);
                    
                    $matchedDomain = str_replace('.' . $matchedTld, '', $parsedString);
                    $matchedDomain = rtrim($matchedDomain, '.');
                    $matchedDomain = ltrim($matchedDomain, '.');
                    
                    if ($matchedTld != 'name' && strpos($matchedDomain, '.')) {
                        $matchedDomain = str_replace('.', '', strrchr($matchedDomain, '.'));
                    }
                    
                    if (strpos($matchedDomain, ' ')) {
                        $matchedDomain = explode(' ', $matchedDomain);
                        $matchedDomain = end($matchedDomain);
                    }
                    
                    $matchedDomainIdn = $IdnaConverter->encode($matchedDomain);
                    
                    break;
                }
                
                if ($tld == $parsedString) {
                    $matchedTld = $tld;
                    $matchedTldIdn = $IdnaConverter->encode($tld);
                    
                    break;
                }
            }
            
            if ($matchedDomain == '' && strlen($matchedDomainIdn) <= 63 && $matchedTld == '') {
                $matchedDomain = $IdnaConverter->decode(preg_replace_callback('/[^a-zA-Z0-9\-\.]/', function (
                        $match) use(&$validHostname)
                {
                    $validHostname = false;
                }, $IdnaConverter->encode($parsedString)));
                $matchedDomainIdn = $IdnaConverter->encode($matchedDomain);
                $matchedTld = $matchedTldIdn = $defaultTld;
            } elseif ($matchedDomain != '' && strlen($matchedDomainIdn) <= 63 && $matchedTld != '') {
                $matchedDomain = $IdnaConverter->decode(preg_replace_callback('/[^a-zA-Z0-9\-\.]/', function (
                        $match) use(&$validHostname)
                {
                    $validHostname = false;
                }, $IdnaConverter->encode($matchedDomain)));
                $matchedDomainIdn = $IdnaConverter->encode($matchedDomain);
            } elseif ($matchedDomain == '' && $matchedTld != '') {
                $validHostname = false;
            } else {
                throw \Novutec\DomainParser\AbstractException::factory('UnparsableString', 'Unparsable domain name.');
            }
            
            $Result = new Result($matchedDomain, $matchedDomainIdn, $matchedTld, $matchedTldIdn, 
                    $validHostname);
        } catch (\Novutec\DomainParser\AbstractException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
            
            $Result = new Result();
            $Result->error = $e->getMessage();
        }
        
        return $Result->get($this->format);
    }

    /**
     * Checks if the domain list exists or cached time is reached
     *
     * @throws OpenFileErrorException
     * @throws WriteFileErrorException
     * @return void
     */
    private function load()
    {
        $filename = $this->path . '/domainparsertld.txt';
        
        if (file_exists($filename)) {
            $this->tldList = unserialize(file_get_contents($filename));
            if (time() - $this->tldList['timestamp'] > $this->cacheTime) {
                $this->reload = true;
            }
        }
        
        if (! file_exists($filename) || $this->reload === true) {
            $this->catchTlds();
            $file = fopen($filename, 'w+');
            
            if ($file === false) {
                throw \Novutec\DomainParser\AbstractException::factory('OpenFile', 'Could not open cache file.');
            }
            
            if (fwrite($file, serialize($this->tldList)) === false) {
                throw \Novutec\DomainParser\AbstractException::factory('WriteFile', 'Could not open cache file for writing.');
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
     * @throws ConnectErrorException
     * @return void
     */
    private function catchTlds()
    {
        $content = @file($this->tldUrl);
        
        if ($content === false) {
            throw \Novutec\DomainParser\AbstractException::factory('Connect', 'Could not catch file from server.');
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
        
        $subtlds = array_merge(array('co.cc', 'com.cc', 'org.cc', 'edu.cc', 'net.cc', 'co.uk', 
                'de.vu', 'me.uk', 'net.uk', 'org.uk', 'sch.uk', 'ac.uk', 'gov.uk', 'nhs.uk', 
                'police.uk', 'mod.uk', 'asn.au', 'com.au', 'net.au', 'id.au', 'org.au', 'edu.au', 
                'gov.au', 'csiro.au', 'co.ke', 'or.ke', 'ne.ke', 'go.ke', 'ac.ke', 'sc.ke', 'me.ke', 
                'mobi.ke', 'info.ke', 'com.tr', 'gen.tr', 'org.tr', 'biz.tr', 'info.tr', 'name.tr', 
                'net.tr', 'web.tr', 'edu.tr', 'ac.nz', 'co.nz', 'geek.nz', 'gen.nz', 'maori.nz', 
                'net.nz', 'org.nz', 'school.nz', 'ac.il', 'co.il', 'org.il', 'net.il', 'k12.il', 'gov.il', 'muni.il', 'idf.il'), $subtlds);
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
     * You may choose between 'object', 'array', 'json', 'serialize' or 'xml' output format
     *
     * @param  string $format
     * @return void
     */
    public function setFormat($format = 'object')
    {
        $this->format = filter_var($format, FILTER_SANITIZE_STRING);
    }

    /**
     * Set encoding of domain name
     * 
     * @param  string $encoding
     * @return void
     */
    public function setEncodng($encoding = 'utf-8')
    {
        $this->encoding = filter_var($encoding, FILTER_SANITIZE_STRING);
    }

    /**
     * Set the throwExceptions flag
     * 
     * Set whether exceptions encounted during processing should be thrown
     * or caught and trapped in the response as a string message.
     * 
     * Default behaviour is to trap them in the response; call this
     * method to have them thrown.
     * 
     * @param  boolean $throwExceptions
     * @return void
     */
    public function throwExceptions($throwExceptions = false)
    {
        $this->throwExceptions = filter_var($throwExceptions, FILTER_VALIDATE_BOOLEAN);
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
        $this->reload = filter_var($reload, FILTER_VALIDATE_BOOLEAN);
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
        $this->cacheTime = filter_var($cacheTime, FILTER_VALIDATE_INT);
    }
}