<?php

namespace AppKit\Http\Server\Resource\Mapper;

use AppKit\Http\Server\Mapper\HttpMapper;

use AppKit\Http\Server\Resource\AbstractHttpResource;
use AppKit\Http\Server\Message\ServerHttpError;
use AppKit\Http\Server\Message\ServerHttpRedirect;
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
        [$match, $resource, $path] = $this -> mapper -> matchRequest($request);

        $this -> log -> debug(
            'Matched request',
            [
                'requestPath' => $request -> getPath(),
                'match' => $match,
                'resource' => $resource !== null ? get_class($resource) : null,
                'path' => $path
            ]
        );

        if(!$match)
            throw new ServerHttpError(404);

        if($path == '/')
            return $resource -> dispatchRequest($request);

        $requestPath = $request -> getPath();
        $rewritePath = substr($requestPath, strlen($path));
        $request -> rewritePath($rewritePath);
        $this -> log -> debug(
            'Rewritten request path {requestPath} to {rewritePath}',
            [
                'requestPath' => $requestPath,
                'rewritePath' => $rewritePath
            ]
        );

        try {
            return $resource -> dispatchRequest($request);
        } catch(AbsoluteHttpRedirect $e) {
            throw $e;
        } catch(ServerHttpRedirect $e) {
            $location = $e -> getLocation();

            if(str_starts_with($location, '/')) {
                $rewriteLocation = $path . $location;

                $this -> log -> debug(
                    'Rewritten redirect location {location} to {rewriteLocation}',
                    [
                        'location' => $location,
                        'rewriteLocation' => $rewriteLocation
                    ]
                );

                throw new ServerHttpRedirect(
                    $rewriteLocation,
                    $e -> getResponse() -> getStatus(),
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
