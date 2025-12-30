<?php

namespace AppKit\Http\Server\Mapper;

use AppKit\Http\Message\HttpError;

class HttpMapper {
    private $mappings = [];

    public function map($path, $handler) {
        $path = '/'.trim($path, '/');

        if(isset($this -> mappings[$path]))
            throw new HttpMapperException("Path $path already in use");
        $this -> mappings[$path] = $handler;

        return $this;
    }

    public function getMappings() {
        return $this -> mappings;
    }

    public function resolveRequest($request) {
        $requestPath = $request -> getUri() -> getPath();

        foreach($this -> mappings as $path => $handler) {
            if(
                $path == '/' ||
                $requestPath == $path ||
                str_starts_with($requestPath, $path.'/')
            ) {
                return [$handler, $path];
            }
        }

        throw new HttpError(404);
    }
}
