<?php

namespace AppKit\Http\Server\Resource;

use AppKit\Http\Server\Mapper\HttpMapper;

use AppKit\Health\HealthIndicatorInterface;
use AppKit\Http\Message\HttpRedirect;

class MapperResource extends AbstractHttpResource implements HealthIndicatorInterface {
    private $mapper;

    function __construct($log) {
        parent::__construct($log -> withModule($this));
        $this -> mapper = new HttpMapper();
    }

    public function checkHealth() {
        $resGroup = [];
        foreach($this -> mapper -> getMappings() as $path => $resource)
            if($resource instanceof HealthIndicatorInterface)
                $resGroup[get_class($resource) . " at $prefix"] = $resource;

        return new HealthCheckResult([
            'Resources' => $resGroup
        ]);
    }

    public function map($path, $resource) {
        $this -> mapper -> map($path, $resource);
        $this -> log -> debug("Mapped $path to ".get_class($resource));

        return $this;
    }

    public function getMappings() {
        return $this -> mapper -> getMappings();
    }

    protected function handleRequest($request) {
        [$resource, $path] = $this -> mapper -> resolveRequest($request);

        if($path != '/') {
            $uri = $request -> getUri();

            $requestPath = substr($uri -> getPath(), strlen($path));
            if($requestPath == '')
                $requestPath = '/';

            $request = $request -> withUri($uri -> withPath($requestPath));
        }

        try {
            return $resource -> dispatchRequest($request);
        } catch(HttpRedirect $e) {
            $location = $e -> getLocation();
            if(str_starts_with($location, '/'))
                throw $e -> withLocation($path . $location);

            throw $e;
        }
    }
}
