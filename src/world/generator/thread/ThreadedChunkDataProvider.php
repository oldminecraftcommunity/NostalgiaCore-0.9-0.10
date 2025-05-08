<?php

abstract class ThreadedChunkDataProvider extends Threaded
{
	public $thread;
	public function __construct(){
		Utils::addThread($this->thread = new ChunkGenerationThread($this));
	}
	
	public function destroy(){
		$this->thread->running = false;
		Utils::removeThread($this->thread);
	}
	
	public function startThread(){
		$this->thread->start();
	}
	
	public abstract function getChunkData($X, $Z);
	public abstract function threadInit();
	
	public function request($X, $Z){
		if(isset($this->requested["$X:$Z"])) return;
		$this->requested["$X:$Z"] = true;
		$this->thread->synchronized(function($t, $x, $z){
			$t->requested[] = [$x, $z];
		}, $this->thread, $X, $Z);
	}
	public $requested = [];
	public $ready = [];
	public function isReady($X, $Z){
		return isset($this->ready["$X:$Z"]);
	}
	
	public function get($X, $Z){
		$ret = $this->ready["$X:$Z"];
		unset($this->ready["$X:$Z"]);
		return $ret;
	}
	
	public function tick(ThreadedGenerator $generator){
		$arr = $this->synchronized(function($thread){
			$arr = [];
			foreach($this->thread->finished as $k => $v){
				$arr[] = $this->thread->finished[$k];
			}
			return (array) $arr;
		}, $this->thread);
		
		foreach($arr as $xzdata){
			$X = $xzdata[0];
			$Z = $xzdata[1];
			
			$this->ready["$X:$Z"] = (array) $xzdata[2];
			unset($this->requested["$X:$Z"]);
		}
		
		$this->synchronized(function($thread, $arr){
			foreach($arr as $k => $v){
				unset($this->thread->finished[$k]);
			}
		}, $this->thread, $arr);
	}
}

