<?php

namespace AppKit\Http\Server\Resource\Mapper;

use AppKit\Http\Server\Mapper\HttpMapper;

use AppKit\Http\Server\Resource\AbstractHttpResource;
use AppKit\Http\Message\HttpError;
use AppKit\Http\Message\HttpRedirect;
use AppKit\Http\Server\Message\AbsoluteHttpRedirect;
use AppKit\Health\HealthIndicatorInterface;

class MapperResource extends AbstractHttpResource {
    private $mapper;

    function __construct($log) {
        parent::__construct($log -> withModule(static::class));
        $this -> mapper = new HttpMapper();
    }

    public function map($path, $resource) {
        $this -> mapper -> map($path, $resource);
        $this -> log -> debug("Mapped $path to ".get_class($resource));

        return $this;
    }

    protected function handleRequest($request) {
        [$matched, $resource, $path] = $this -> mapper -> matchRequest($request);
        if(!$matched)
            throw new HttpError(404);

        if($path != '/') {
            $requestPath = substr($request -> getPath(), strlen($path));
            if($requestPath == '')
                $requestPath = '/';

            $request -> rewritePath($requestPath);
        }

        try {
            return $resource -> dispatchRequest($request);
        } catch(AbsoluteHttpRedirect $e) {
            throw $e;
        } catch(HttpRedirect $e) {
            $location = $e -> getLocation();
            if(str_starts_with($location, '/')) {
                throw new HttpRedirect(
                    $path . $location,
                    $e -> getStatus(),
                    previous: $e
                );
            }

            throw $e;
        }
    }

    protected function getAdditionalHealthData() {
        $data = [];

        foreach($this -> mapper -> getMappings() as $path => $resource)
            if($resource instanceof HealthIndicatorInterface)
                $data['Resources'][get_class($resource) . " at $path"] = $resource;

        return $data;
    }
}
