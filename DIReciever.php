<?php

class DIReciever {
    
    /**
     * Undocumented function
     *
     * @DI $var globalTestVar
     * @DI $service ExampleService
     *
     * @return void
     */
    public function test($var, $service)
    {
        echo $var;
        $service->execute();
    }
}