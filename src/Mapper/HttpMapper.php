<?php

namespace AppKit\Http\Server\Mapper;

class HttpMapper {
    private $mappings = [];
    private $mappingsSorted = false;

    public function map($path, $handler) {
        $path = '/'.trim($path, '/');

        if(isset($this -> mappings[$path]))
            throw new HttpMapperException("Path $path already in use");

        $this -> mappings[$path] = $handler;
        $this -> mappingsSorted = false;

        return $this;
    }

    public function getMappings() {
        return $this -> mappings;
    }

    public function matchRequest($request) {
        if(! $this -> mappingsSorted)
            $this -> sortMappings();

        $requestPath = $request -> getPath();

        foreach($this -> mappings as $path => $handler) {
            if(
                $path == '/' ||
                $requestPath == $path ||
                str_starts_with($requestPath, $path.'/')
            ) {
                return [true, $handler, $path];
            }
        }

        return [false, null, null];
    }

    private function sortMappings() {
        uksort($this -> mappings, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        $this -> mappingsSorted = true;
    }
}
