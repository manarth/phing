<?php
/*
 *  $Id: $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
 */
require_once('phing/util/properties/PropertySet.php');
require_once('phing/util/properties/PropertyExpansionIterator.php');

/**
 * A class that can expand ${}-style references in arbitrary strings with the 
 * corresponding values from a PropertySet.
 * 
 * As this class already "contains" a property set, it also acts as a decorator 
 * for it and takes care of expanding propery references in the property values
 * themselves.
 */
class PropertyExpansionHelper implements PropertySet {
	protected $props;
	
	public function __construct(PropertySet $props) {
		$this->props = $props;
	}

	public function offsetGet($key) { return $this->expand($this->props->offsetGet($key)); }
	public function offsetSet($key, $value) { $this->props->offsetSet($key, $value); }
	public function offsetExists($key) { return $this->props->offsetExists($key); }
	public function offsetUnset($key) { $this->props->offsetUnset($key); }
	public function getIterator() { return new PropertyExpansionIterator($this, $this->props->getIterator()); }
	public function isEmpty() { return $this->props->isEmpty(); }
	public function keys() { return $this->props->keys(); }
	
	/**
     * Replaces ${} style constructions in the given value with the
     * string value of the corresponding data types.
     *
     * @param value The string to be scanned for property references.
     *              May be <code>null</code>.
     *
     * @return the given string with embedded property names replaced
     *         by values, or <code>null</code> if the given string is
     *         <code>null</code>.
     *
     * @exception BuildException if the given value has an unclosed
     *                           property name, e.g. <code>${xxx</code>
     */
	public function expand($b) {
        if ($b === null) 
            return null;
        
        if (is_array($b))
        	return $this->expandArray($b);
        
        $this->refStack = array();
        
        return $this->match($b);
	}
	
	protected function match($b) {
		if (strpos($b, '${') !== false)
            $b = preg_replace_callback('/\$\{([^\$}]+)\}/', array($this, 'replacePropertyCallback'), $b);
        
        return $b;        
    }
    
    protected function replacePropertyCallback($matches) {
		$propertyName = $matches[1];
		
		if (in_array($propertyName, $this->refStack))
			$this->circularException();
		
		if (!isset($this->props[$propertyName]))			
			return $matches[0];
		
		$propertyValue = $this->props[$propertyName];
		
        if (is_bool($propertyValue))
            $propertyValue = $propertyValue ? "true" : "false";
        
		else if (is_array($propertyValue))
        	$propertyValue = implode(',', $propertyValue); 

        else {
        	array_push($this->refStack, $propertyName);
        	$propertyValue = $this->match($propertyValue);
        	array_pop($this->refStack);
        }

        return $propertyValue;
    }

    protected function circularException() {
    	$n = array_pop($this->refStack);
    	throw new BuildException("Property $n was circularly defined: " . implode(" => ", $this->refStack));	
    }
    
    protected function expandArray(array $a) {
		$r = array();
		foreach ($a as $key => $value) {
			$r[$key] = $this->expand($value);
		}
		return $r;
	}
}