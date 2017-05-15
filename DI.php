<?php

class DI {

    protected $services = [];
    protected $registry = [];

    public function bindService(&$service, $class = null)
    {
        $reflection = new \ReflectionObject($service);

        if ($class !== null && !is_string($class)) {
            throw new \TypeError('$class must be a string');
        }

        if ($class === null) {
            if ($reflection->inNamespace()) {
                $class = $reflection->getName();
            } else {
                $class = $reflection->getShortName();
            }
        }

        $this->services[$class] = &$service;

        return $this->services[$class];
    }

    public function createService($class)
    {
        $service = new $class();
        var_dump($class);
        $this->bindService($service, $class);

        return;
    }

    public function register(&$var, $name)
    {
        if (!is_string($name)) {
            throw new \TypeError('$name must be a string');
        }

        $this->registry[$name] = &$var;
        return $this->registry[$name];
    }

    private function callSplitter($call, &$static)
    {
        if (strpos($call, '()')) {
            $call = str_replace('()', '', $call);
        }

        if (strpos($call, '::') !== false) {
            $split = explode('::', $call);
            $static = true;
        } else if (strpos($call, '->') !== false) {
            $split = explode('->', $call);
        } else {
            return [$call, '__construct'];
        }

        return $split;
    }
    
    public function inject($callString, $args = [], $instance = null)
    {
        $static = false;
        $call = $this->callSplitter($callString, $static);

        $method = new ReflectionMethod($call[0], $call[1]);
        $params = $method->getParameters();

        $docParams = $this->parseDocForParams($method->getDocComment());

        $injectParams = $this->injectParams($params, $docParams, $args);
        
        if ($static) {
            return $method->invokeArgs(null, $injectParams);

        } else {
            if ($instance === null) {
                $class = new ReflectionClass($call[0]);

                if (isset($call[1]) && $call[1] == '__construct') {
                    $instance = $class->newInstanceArgs($injectParams);
                } else {
                    $instance = $class->newInstance();
                }
            }

            return $method->invokeArgs($instance, $injectParams);
        }
    }

    private function parseDocForParams($doc)
    {
        $matches = $docParams = [];
        $found = preg_match_all('/@DI\s+\$(\w+)\s+(\w+)/', $doc, $matches);
        if ($found) {
            $docParams = array_combine($matches[1], $matches[2]);
        }

        return $docParams;
    }

    private function injectParams($reflectionParams, $docParams, $existingParams)
    {
        $finalParams = [];
        foreach ($reflectionParams as $param) {
            $position = $param->getPosition();
            $name = $param->getName();
            $ref = $param->isPassedByReference();

            if (isset($docParams[$name])) {
                if (isset($this->registry[$docParams[$name]])) {
                    if ($ref) {
                        $finalParams[$position] = &$this->registry[$docParams[$name]];
                    } else {
                        $finalParams[$position] = $this->registry[$docParams[$name]];
                    }
                    continue;
                } else {
                    trigger_error('DI-annotated value wasn\'t found in registry', E_USER_WARNING);
                }
            }

            if (isset($existingParams[$position])) {
                if ($ref) {
                    $finalParams[$position] = &$existingParams[$position];
                } else {
                    $finalParams[$position] = $existingParams[$position];
                }

                continue;
            }

            if (isset($this->registry[$name])) {
                if ($ref) {
                    $finalParams[$position] = &$this->registry[$name];
                } else {
                    $finalParams[$position] = $this->registry[$name];
                }
                
                continue;
            }

            $class = null;
            if ($paramClass = $param->getClass()) {
                $class = $paramClass->name;
            }

            if ($class !== null) {
                $createdService = false;
                
                if (!isset($services[$class]) && class_exists($class)) {
                    
                    $this->createService($class);
                    $createdService = true;
                }

                if ($createdService || isset($services[$class])) {
                    if ($ref) {
                        $finalParams[$position] = &$this->services[$class];
                    } else {
                        $finalParams[$position] = $this->services[$class];
                    }
                        
                    continue;
                }
            }

            if ($param->isDefaultValueAvailable()) {
                $finalParams[$position] = $param->getDefaultValue();
            }

            if (class_exists($class)) {
                $finalParams[$position] = new $class();
            }

            $finalParams[$position] = null;
        }

        return $finalParams;
    }
}