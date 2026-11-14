<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_one_id', 'user_two_id']);
        });

        Schema::table('service_requests', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->nullable()->after('provider_id')->constrained()->nullOnDelete();
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('type', 30)->default('text');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['receiver_id', 'read_at']);
        });

        DB::table('service_requests')
            ->join('services', 'services.id', '=', 'service_requests.service_id')
            ->select([
                'service_requests.id',
                'service_requests.service_id',
                'service_requests.requester_id',
                'service_requests.provider_id',
                'service_requests.status',
                'service_requests.created_at',
                'services.title',
            ])
            ->orderBy('service_requests.id')
            ->chunk(100, function ($requests): void {
                foreach ($requests as $serviceRequest) {
                    $userIds = [(int) $serviceRequest->requester_id, (int) $serviceRequest->provider_id];
                    sort($userIds, SORT_NUMERIC);
                    $timestamp = $serviceRequest->created_at ?? now();
                    $conversation = DB::table('conversations')
                        ->where('user_one_id', $userIds[0])
                        ->where('user_two_id', $userIds[1])
                        ->first();

                    if ($conversation === null) {
                        $conversationId = DB::table('conversations')->insertGetId([
                            'user_one_id' => $userIds[0],
                            'user_two_id' => $userIds[1],
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                    } else {
                        $conversationId = $conversation->id;
                    }

                    DB::table('service_requests')
                        ->where('id', $serviceRequest->id)
                        ->update(['conversation_id' => $conversationId]);

                    DB::table('conversation_messages')->insert([
                        'conversation_id' => $conversationId,
                        'service_request_id' => $serviceRequest->id,
                        'sender_id' => $serviceRequest->requester_id,
                        'receiver_id' => $serviceRequest->provider_id,
                        'message' => 'Service request for '.$serviceRequest->title.' ('.$serviceRequest->status.').',
                        'type' => 'service_request',
                        'read_at' => $timestamp,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    DB::table('messages')
                        ->where('service_request_id', $serviceRequest->id)
                        ->orderBy('id')
                        ->get()
                        ->each(function ($message) use ($conversationId): void {
                            DB::table('conversation_messages')->insert([
                                'conversation_id' => $conversationId,
                                'service_request_id' => $message->service_request_id,
                                'sender_id' => $message->sender_id,
                                'receiver_id' => $message->receiver_id,
                                'message' => $message->message,
                                'type' => 'text',
                                'read_at' => $message->created_at,
                                'created_at' => $message->created_at,
                                'updated_at' => $message->updated_at,
                            ]);
                        });
                }
            });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn('conversation_id');
        });

        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
