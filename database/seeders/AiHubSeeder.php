<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentWorkflow;
use App\Models\Suite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AiHubSeeder extends Seeder
{
    public function run(): void
    {
        // Create account
        $account = Account::firstOrCreate(['name' => 'AI Hub Account']);

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@aihub.com'],
            [
                'account_id' => $account->id,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => bcrypt('password'),
                'subscription_tier' => ['enterprise'],
                'owner' => true,
            ]
        );
        $admin->assignRole('admin');

        // Create regular user
        $user = User::firstOrCreate(
            ['email' => 'user@aihub.com'],
            [
                'account_id' => $account->id,
                'first_name' => 'Test',
                'last_name' => 'User',
                'password' => bcrypt('password'),
                'subscription_tier' => ['pro'],
                'owner' => false,
            ]
        );
        $user->assignRole('user');

        // Create sample suite
        $suite = Suite::create([
            'name' => 'Research Assistant Suite',
            'description' => 'A comprehensive research assistant with web search and RAG capabilities',
            'slug' => 'research-assistant',
            'status' => 'active',
            'subscription_tiers' => ['pro', 'enterprise'],
            'created_by' => $admin->id,
        ]);

        // Create agents
        $agent1 = Agent::create([
            'suite_id' => $suite->id,
            'name' => 'Web Research Agent',
            'description' => 'Searches the web for current information',
            'slug' => 'web-research',
            'model_provider' => 'openai',
            'model_name' => 'gpt-4-turbo',
            'system_prompt' => 'You are a research assistant. Use the provided web search results to answer questions accurately and cite sources.',
            'enable_rag' => false,
            'enable_web_search' => true,
            'order' => 1,
            'is_active' => true,
            'model_config' => [
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ],
        ]);

        $agent2 = Agent::create([
            'suite_id' => $suite->id,
            'name' => 'Document Analysis Agent',
            'description' => 'Analyzes uploaded documents using RAG',
            'slug' => 'document-analysis',
            'model_provider' => 'openai',
            'model_name' => 'gpt-4',
            'system_prompt' => 'You are a document analysis expert. Use the provided document context to answer questions accurately.',
            'enable_rag' => true,
            'enable_web_search' => false,
            'order' => 2,
            'is_active' => true,
            'model_config' => [
                'temperature' => 0.5,
                'max_tokens' => 3000,
            ],
        ]);

        $agent3 = Agent::create([
            'suite_id' => $suite->id,
            'name' => 'Report Generator Agent',
            'description' => 'Generates comprehensive reports from research',
            'slug' => 'report-generator',
            'model_provider' => 'openai',
            'model_name' => 'gpt-4-turbo',
            'system_prompt' => 'You are a report writing expert. Create well-structured, comprehensive reports based on the provided information.',
            'enable_rag' => false,
            'enable_web_search' => false,
            'order' => 3,
            'is_active' => true,
            'model_config' => [
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ],
        ]);

        // Create workflow
        $workflow = AgentWorkflow::create([
            'suite_id' => $suite->id,
            'name' => 'Complete Research Workflow',
            'description' => 'Web research → Document analysis → Report generation',
            'agent_sequence' => [$agent1->id, $agent2->id, $agent3->id],
            'workflow_config' => [
                'stop_on_error' => false,
                'max_iterations' => 10,
            ],
            'is_active' => true,
        ]);

        // Create another suite
        $suite2 = Suite::create([
            'name' => 'Customer Support Suite',
            'description' => 'AI-powered customer support with knowledge base',
            'slug' => 'customer-support',
            'status' => 'active',
            'subscription_tiers' => ['basic', 'pro', 'enterprise'],
            'created_by' => $admin->id,
        ]);

        Agent::create([
            'suite_id' => $suite2->id,
            'name' => 'Support Agent',
            'description' => 'Handles customer inquiries',
            'slug' => 'support-agent',
            'model_provider' => 'openai',
            'model_name' => 'gpt-3.5-turbo',
            'system_prompt' => 'You are a helpful customer support agent. Be friendly, professional, and solution-oriented.',
            'enable_rag' => true,
            'enable_web_search' => false,
            'order' => 1,
            'is_active' => true,
            'model_config' => [
                'temperature' => 0.7,
                'max_tokens' => 1500,
            ],
        ]);

        $this->command->info('AI Hub seeded successfully!');
        $this->command->info('Admin: admin@aihub.com / password');
        $this->command->info('User: user@aihub.com / password');
    }
}

