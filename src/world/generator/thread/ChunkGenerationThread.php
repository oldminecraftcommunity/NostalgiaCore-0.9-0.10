<?php

class ChunkGenerationThread extends Thread{
	
	public $requested = [];
	public $finished = [];
	
	public $provider;
	
	public function __construct(ThreadedChunkDataProvider $provider){
		$this->provider = $provider;
	}
	public function run(){
		$this->provider->threadInit();
		cont:
		if(count($this->requested) > 0){
			$chunks = $this->synchronized(function(){
				$chunks = [];
				foreach($this->requested as $k => $c){
					$x = $c[0];
					$z = $c[1];
					$chunks[] = [$x, $z];
					unset($this->requested[$k]);
				}
				
				return $chunks;
			});
			foreach($chunks as $c){
				$chunk = $this->provider->getChunkData($c[0], $c[1]);
				$x = $c[0];
				$z = $c[1];
				$this->synchronized(function() use ($chunk, $x, $z){
					$this->finished[] = [$x, $z, $chunk];
				});
			}
		}
		goto cont;
		
	}
}