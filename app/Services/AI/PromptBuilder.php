<?php

namespace App\Services\AI;

use App\Models\Agent;

class PromptBuilder
{
    /**
     * Base system prompt for ALL agents
     */
    protected const BASE_SYSTEM_PROMPT = <<<'PROMPT'
You are an Evidence-Based Analysis Agent.
You MUST always combine:
• User question
• Uploaded document chunks (RAG)
• Web search results
• External API data
based on the features enabled for this Agent.

STRICT RULES:
1. DO NOT hallucinate facts, numbers, regulations, or events.
2. Every claim must be backed by a specific source tag:
 • [DOC] for uploaded document evidence
 • [WEB] for web search evidence
 • [API] for external API evidence
 • [AGENT] for previous agent output in workflow chains
 • [ASSUMPTION] only if explicitly stated by user
3. If data cannot be verified, you MUST write: "INSUFFICIENT VERIFIED DATA".
4. UNCITED CLAIMS ARE NOT PERMITTED.
5. If web search or API features are disabled for this Agent, do not fabricate data — use only available sources.
6. Use ONLY the data available in:
 • User documents
 • Real web search results
 • External API responses
7. Never rely on "general knowledge" unless explicitly supported by cited results.

MANDATORY REPORT STRUCTURE (ALL AGENTS MUST FOLLOW):
1. Executive Summary
 • Summary of findings
 • List of data sources used
 • List of missing or insufficient data
2. Data Sources Used
 • Uploaded documents (list filenames + short excerpts)
 • Web search results (title + URL)
 • External APIs used (API name + description)
 If a section uses only one type of data, explicitly say so.
3. Verified Insights Only
 • Each statement MUST include [DOC], [WEB], [API], or [AGENT].
4. Citations & References
 • Full URLs
 • Document snippets
 • API response snippets 
5. Insufficient Data
 • What could not be verified
 • Why
 • What would be needed to verify it
6. Final Conclusion
 • Only evidence-backed interpretation
 • No speculation
 • No invented statistics
 • Highlight contradictions between sources

RESPONSE FORMAT:
Always output structured sections.
NEVER output free-flowing paragraphs without sections.

If this Agent is part of a workflow chain:
• Accept previous Agent output as validated input.
• Use it as an additional data source called [AGENT].
PROMPT;

    /**
     * Agent-specific prompts
     */
    protected const AGENT_PROMPTS = [
        'market_intelligence' => <<<'PROMPT'
You are the Market Intelligence Agent.
Your job is to produce a complete, evidence-based market analysis by fusing:
• User documents (DOC)
• Web search (WEB)
• Crunchbase or other external APIs (API)

TASKS:
1. Identify and verify market size (TAM, SAM, SOM) using:
 • Document data (DOC)
 • Verified market numbers from WEB/API
 If no real numbers are found → mark as "INSUFFICIENT VERIFIED DATA".
2. Identify global and regional competitors.
3. For each competitor, retrieve (via API or WEB):
 • Funding
 • Valuation
 • Investors
 • Last financing round
4. Identify acquisitions, partnerships, and trends (WEB).
5. Fuse findings into a structured Market Analysis Report using the GLOBAL REPORT FORMAT.

RULE:
If ANY metric cannot be verified:
→ "INSUFFICIENT VERIFIED DATA" 
Never invent market size numbers.
PROMPT,

        'regulatory_pathway' => <<<'PROMPT'
You are the Regulatory Strategy Agent.
You must create a fully verified regulatory pathway using:
• User documents (DOC)
• Real FDA/EMA sources (WEB/API)

TASKS:
1. Determine device classification (FDA Class I/II/III) using FDA databases (API/WEB).
2. Identify predicate devices from real records.
3. Extract device characteristics from user documents (DOC).
4. Map required validation and performance testing.
5. List applicable standards ONLY IF confirmed by WEB/API.
6. Build an evidence-backed Regulatory Pathway Report using the GLOBAL REPORT FORMAT.

RULES:
• No guessed classifications
• No invented regulatory steps
• No fake standards
• Every claim must be VERIFIED and CITED
If uncertain → "INSUFFICIENT VERIFIED DATA"
PROMPT,

        'technical_feasibility' => <<<'PROMPT'
You are the Technical Feasibility Agent.
Your job is to evaluate the scientific and engineering feasibility of the technology using:
• Technical PDFs, protocols, data (DOC)
• Scientific sources (WEB)
• Patent data (API)

TASKS:
1. Extract and summarize technical mechanisms (DOC).
2. Identify known limitations or risks using scientific WEB results.
3. Retrieve relevant patents (API) and summarize verified claims.
4. Evaluate scalability of manufacturing using DOC+WEB.
5. Produce a Technical Feasibility Report using the GLOBAL REPORT FORMAT.

RULE:
You must not invent mechanisms, biochemical steps, CRISPR behaviors, or scientific facts.
If evidence missing → "INSUFFICIENT VERIFIED DATA".
PROMPT,

        'valuation' => <<<'PROMPT'
You are the Valuation Agent.
Your job is to generate a strictly evidence-backed valuation using:
• Financial model documents (DOC)
• Comparable company data (API/WEB)

TASKS:
1. Perform DCF using ONLY the values provided within the user's financial documents (DOC).
2. Fetch comparable companies:
 • Funding
 • Valuation
 • Investors
 • Revenue
from Crunchbase or API/WEB.
3. Build a Valuation Report using the GLOBAL REPORT FORMAT.
4. Produce a valuation range using:
 • DCF (DOC)
 • Comparable analysis (API/WEB)

RULE:
No invented numbers.
If inputs are missing, list them in "INSUFFICIENT DATA".
PROMPT,
    ];

    /**
     * Build complete system prompt for agent
     */
    public function buildSystemPrompt(Agent $agent, ?string $previousAgentOutput = null, ?array $tools = null): string
    {
        $prompt = self::BASE_SYSTEM_PROMPT . "\n\n";

        // Add agent-specific prompt if available
        $agentType = $this->detectAgentType($agent);
        if ($agentType && isset(self::AGENT_PROMPTS[$agentType])) {
            $prompt .= self::AGENT_PROMPTS[$agentType] . "\n\n";
        } elseif ($agent->system_prompt) {
            // Use custom system prompt from agent config
            $prompt .= $agent->system_prompt . "\n\n";
        }

        // Add tool usage instructions if tools are available
        if (!empty($tools)) {
            $prompt .= "TOOL USAGE INSTRUCTIONS:\n";
            $prompt .= "You have access to external API tools that you MUST use when relevant to answer the user's question.\n";
            $prompt .= "When a user asks about:\n";
            $prompt .= "- Drugs, medications, FDA information: Use fda_searchDrug, fda_getAllDrugs, or fda_getRecallInfo tools\n";
            $prompt .= "- Medical devices: Use fda_searchDevice tool\n";
            $prompt .= "- Companies: Use crunchbase_searchCompany tool\n";
            $prompt .= "- Patents: Use patents_searchPatent tool\n";
            $prompt .= "- News: Use news_searchNews tool\n";
            $prompt .= "\n";
            $prompt .= "IMPORTANT: Always call the appropriate tool(s) BEFORE generating your final response.\n";
            $prompt .= "Do not say 'No external API data' if you haven't called the tools yet.\n";
            $prompt .= "Use the tool results to provide accurate, verified information.\n\n";
        }

        // Add workflow context if part of chain
        if ($previousAgentOutput) {
            $prompt .= "PREVIOUS AGENT OUTPUT (use as [AGENT] source):\n";
            $prompt .= $previousAgentOutput . "\n\n";
        }

        // Add context priority rules
        $prompt .= $this->getContextPriorityRules($agent);

        return $prompt;
    }

    /**
     * Detect agent type from name/description
     */
    protected function detectAgentType(Agent $agent): ?string
    {
        $name = strtolower($agent->name);
        $description = strtolower($agent->description ?? '');

        if (str_contains($name, 'market') || str_contains($description, 'market')) {
            return 'market_intelligence';
        }
        if (str_contains($name, 'regulatory') || str_contains($description, 'regulatory')) {
            return 'regulatory_pathway';
        }
        if (
            str_contains($name, 'technical') || str_contains($name, 'feasibility') ||
            str_contains($description, 'technical') || str_contains($description, 'feasibility')
        ) {
            return 'technical_feasibility';
        }
        if (str_contains($name, 'valuation') || str_contains($description, 'valuation')) {
            return 'valuation';
        }

        return null;
    }

    /**
     * Get context priority rules based on agent configuration
     */
    protected function getContextPriorityRules(Agent $agent): string
    {
        $rules = "CONTEXT PRIORITY RULES:\n";
        $rules .= "When DOC, WEB, and API data conflict:\n";
        $rules .= "1. DOC (user documents) take highest priority.\n";
        $rules .= "2. API (structured sources like Crunchbase, FDA, Patents) next.\n";
        $rules .= "3. WEB search last.\n\n";
        $rules .= "If conflict is detected:\n";
        $rules .= "• Highlight the contradiction, but side with DOC unless DOC clearly states uncertainty.\n\n";
        $rules .= "If an API returns no data:\n";
        $rules .= "• Do not guess or create fake companies.\n";
        $rules .= "• Mark that section as 'Insufficient Data – API returned no matching records.'\n";

        return $rules;
    }

    /**
     * Build user prompt with context
     */
    public function buildUserPrompt(
        string $userQuestion,
        array $ragContext = [],
        array $webSearchResults = [],
        array $externalApiData = [],
        array $chatHistory = [],
        bool $hasTools = false
    ): string {
        $prompt = "";

        // Add chat history for context continuity
        if (!empty($chatHistory)) {
            $prompt .= "PREVIOUS CONVERSATION CONTEXT:\n";
            foreach ($chatHistory as $message) {
                $role = strtoupper($message['role'] ?? 'user');
                $content = $message['content'] ?? '';
                $prompt .= "{$role}: {$content}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "USER QUESTION: " . $userQuestion . "\n\n";

        // Add RAG context
        if (!empty($ragContext)) {
            $prompt .= "UPLOADED DOCUMENT EVIDENCE [DOC]:\n";
            foreach ($ragContext as $index => $context) {
                // Handle both array format (with metadata) and string format (legacy)
                $content = is_array($context) ? ($context['content'] ?? $context) : $context;
                $metadata = is_array($context) ? ($context['metadata'] ?? []) : [];

                $prompt .= "[DOC-" . ($index + 1) . "] " . $content . "\n";
                if (isset($metadata['file_name'])) {
                    $prompt .= "Source: " . $metadata['file_name'] . "\n";
                } elseif (is_array($context) && isset($context['file_name'])) {
                    // Fallback: check if filename is directly in context
                    $prompt .= "Source: " . $context['file_name'] . "\n";
                }
            }
            $prompt .= "\n";
        } else {
            // Explicitly state if no documents were found
            $prompt .= "UPLOADED DOCUMENT EVIDENCE [DOC]:\n";
            $prompt .= "No relevant document chunks found for this query.\n";
            $prompt .= "If documents were uploaded, they may not contain relevant information for this question.\n\n";
        }

        // Add web search results
        if (!empty($webSearchResults)) {
            $prompt .= "WEB SEARCH EVIDENCE [WEB]:\n";
            foreach ($webSearchResults as $index => $result) {
                $prompt .= "[WEB-" . ($index + 1) . "] " . ($result['title'] ?? '') . "\n";
                $prompt .= "Snippet: " . ($result['snippet'] ?? '') . "\n";
                $prompt .= "URL: " . ($result['link'] ?? $result['url'] ?? '') . "\n\n";
            }
        }

        // Add external API data (only if not using tools - tools will be called dynamically)
        if (!$hasTools) {
            if (!empty($externalApiData)) {
                $prompt .= "EXTERNAL API EVIDENCE [API]:\n";
                foreach ($externalApiData as $index => $data) {
                    $source = $data['source'] ?? 'Unknown';
                    $status = $data['status'] ?? 'UNKNOWN';
                    $apiData = $data['data'] ?? null;

                    $prompt .= "[API-" . ($index + 1) . "] Source: " . strtoupper($source) . "\n";

                    if ($status === 'FAILED_OR_EMPTY' || empty($apiData)) {
                        $prompt .= "Status: No data returned or API call failed.\n";
                        if (isset($data['error'])) {
                            $prompt .= "Error: " . $data['error'] . "\n";
                        }
                        if (isset($data['message'])) {
                            $prompt .= "Message: " . $data['message'] . "\n";
                        }
                    } else {
                        $prompt .= "Status: SUCCESS - Data retrieved from " . strtoupper($source) . " API\n";

                        // Format drug data more clearly
                        if ($source === 'fda' && is_array($apiData)) {
                            $prompt .= "Number of records: " . count($apiData) . "\n";
                            $prompt .= "Drug Information:\n";
                            foreach (array_slice($apiData, 0, 10) as $drugIndex => $drug) { // Show first 10 drugs
                                $prompt .= "  Drug " . ($drugIndex + 1) . ":\n";
                                if (!empty($drug['brand_name'])) {
                                    $prompt .= "    Brand Name: " . $drug['brand_name'] . "\n";
                                }
                                if (!empty($drug['generic_name'])) {
                                    $prompt .= "    Generic Name: " . $drug['generic_name'] . "\n";
                                }
                                if (!empty($drug['substance_name'])) {
                                    $prompt .= "    Substance: " . $drug['substance_name'] . "\n";
                                }
                                if (!empty($drug['indications_and_usage']) && is_array($drug['indications_and_usage'])) {
                                    $prompt .= "    Indications: " . implode('; ', array_slice($drug['indications_and_usage'], 0, 2)) . "\n";
                                }
                                if (!empty($drug['description']) && is_array($drug['description'])) {
                                    $desc = implode(' ', array_slice($drug['description'], 0, 1));
                                    $prompt .= "    Description: " . substr($desc, 0, 200) . "...\n";
                                }
                                $prompt .= "\n";
                            }
                            if (count($apiData) > 10) {
                                $prompt .= "  ... and " . (count($apiData) - 10) . " more drug records.\n";
                            }
                        } else {
                            // For other APIs, use JSON format
                            $prompt .= "Data: " . json_encode($apiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                        }
                    }

                    if (isset($data['url'])) {
                        $prompt .= "API URL: " . $data['url'] . "\n";
                    }
                    if (isset($data['endpoint'])) {
                        $prompt .= "Endpoint Type: " . $data['endpoint'] . "\n";
                    }
                    $prompt .= "\n";
                }
            } else {
                $prompt .= "EXTERNAL API EVIDENCE [API]:\n";
                $prompt .= "No external API data was retrieved. External APIs may not be configured, or the API calls failed.\n\n";
            }
        } else {
            // When tools are available, tell AI to use them
            $prompt .= "EXTERNAL API TOOLS AVAILABLE:\n";
            $prompt .= "You have access to external API tools. Use the appropriate tool(s) to fetch data before answering.\n";
            $prompt .= "Call the tools now to get the information needed to answer the user's question.\n\n";
        }

        $prompt .= "\nINSTRUCTIONS:\n";
        $prompt .= "Answer the user question using ONLY the evidence provided above.\n";
        $prompt .= "Every claim MUST be tagged with [DOC], [WEB], [API], or [AGENT].\n";
        $prompt .= "If evidence is missing, state 'INSUFFICIENT VERIFIED DATA'.\n";
        $prompt .= "Follow the MANDATORY REPORT STRUCTURE.\n";

        return $prompt;
    }
}

