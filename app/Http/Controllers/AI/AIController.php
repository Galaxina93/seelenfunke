<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AI\AIFunctionsRegistry;

class AIController extends Controller
{
    /**
     * Retrieve the JSON Schema describing all available AI Tools.
     */
    public function schema(Request $request)
    {
        // Here you would implement rudimentary security check. 
        // Example: if($request->header('X-AI-TOKEN') !== env('AI_SECRET')) return abort(403);

        return response()->json([
            'status' => 'success',
            'tools' => AIFunctionsRegistry::getSchema(),
        ]);
    }

    /**
     * Execute an AI Tool call coming from Ollama/Python Script.
     */
    public function execute(Request $request)
    {
        // Example Payload: 
        // { "function": "get_system_health", "args": { "param1": "val1" } }
        
        $request->validate([
            'function' => 'required|string',
            'args' => 'sometimes|array'
        ]);

        $functionName = $request->input('function');
        $args = $request->input('args', []);

        try {
            // Forward execution to the registry
            $result = AIFunctionsRegistry::execute($functionName, $args);

            return response()->json([
                'status' => 'success',
                'function' => $functionName,
                'result' => $result
            ]);

        } catch (\InvalidArgumentException $e) {
            // Function not found
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
            
        } catch (\Exception $e) {
            // Internal execution error
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Frontend Endpoint: Receives a text prompt, sends it to Ollama, and returns the response.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000'
        ]);

        $prompt = $request->input('prompt');

        $agent = new \App\Services\AI\OllamaAgent();
        $result = $agent->ask($prompt);

        return response()->json([
            'status' => 'success',
            'response' => $result['response'],
            'context_data' => $result['context_data'] ?? []
        ]);
    }
}
