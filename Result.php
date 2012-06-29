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
 * DomainParserResult
 *
 * @category   Novutec
 * @package    DomainParser
 * @copyright  Copyright (c) 2007 - 2012 Novutec Inc. (http://www.novutec.com)
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 */
class Result
{

    /**
     * Domain name
     * 
     * @var string
     * @access protected
     */
    protected $domain;

    /**
     * Domain name IDN converted
     *
     * @var string
     * @access protected
     */
    protected $idn_domain;

    /**
     * Top-level domain name
     *
     * @var string
     * @access protected
     */
    protected $tld;

    /**
     * Top-level domain name IDN converted
     *
     * @var string
     * @access protected
     */
    protected $idn_tld;

    /**
     * Constructs a new object from parsed domain name by DomainParser
     * 
     * @param  string $domain
     * @param  string $idn_domain
     * @param  string $tld
     * @param  string $idn_tld
     * @return void
     */
    public function __construct($domain = '', $idn_domain = '', $tld = '', $idn_tld = '')
    {
        $this->domain = $domain;
        $this->idn_domain = $idn_domain;
        $this->tld = $tld;
        $this->idn_tld = $idn_tld;
    }

    /**
	 * Writing data to properties
	 *
	 * @param  string $name
	 * @param  mixed $value
	 * @return void
	 */
    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    /**
	 * Checking data
	 *
	 * @param  mixed $name
	 * @return boolean
	 */
    public function __isset($name)
    {
        return isset($this->{$name});
    }

    /**
	 * Reading data from properties
	 *
	 * @param  string $name
	 * @return void
	 */
    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }
        
        return null;
    }

    /**
     * Convert properties to json
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Convert properties to array
     *
     * @return array
     */
    public function toArray()
    {
        $output['domain'] = $this->domain;
        $output['idn_domain'] = $this->idn_domain;
        $output['tld'] = $this->tld;
        $output['idn_tld'] = $this->idn_tld;
        
        return $output;
    }
}