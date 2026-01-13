#!/usr/bin/env node

/**
 * Socket.IO Server Test Script
 * This script tests if the Socket.IO server is working correctly
 */

const io = require('socket.io-client');

console.log('🔌 Socket.IO Server Test\n');
console.log('Connecting to http://localhost:3000...\n');

const socket = io('http://localhost:3000');

let testsPassed = 0;
let testsFailed = 0;

// Test 1: Connection
socket.on('connect', () => {
    console.log('✅ Test 1: Connection successful');
    console.log(`   Socket ID: ${socket.id}\n`);
    testsPassed++;

    // Test 2: User Join
    console.log('📤 Test 2: Sending user:join event...');
    socket.emit('user:join', {
        userId: 999,
        userName: 'Test User'
    });
});

// Test 3: Online Users
socket.on('users:online', (users) => {
    console.log('✅ Test 3: Received users:online event');
    console.log(`   Online users: ${users.length}`);
    console.log(`   Users: ${JSON.stringify(users, null, 2)}\n`);
    testsPassed++;

    // Test 4: Join Group
    console.log('📤 Test 4: Joining test group...');
    socket.emit('group:join', {
        groupId: 999,
        userId: 999,
        userName: 'Test User'
    });

    setTimeout(() => {
        // Test 5: Send Message
        console.log('📤 Test 5: Sending test message...');
        socket.emit('message:send', {
            groupId: 999,
            userId: 999,
            userName: 'Test User',
            messageType: 'text',
            message: 'This is a test message!'
        });
    }, 500);
});

// Test 6: Message Received
socket.on('message:received', (data) => {
    console.log('✅ Test 6: Received message:received event');
    console.log(`   Message: ${data.message}`);
    console.log(`   Message Type: ${data.messageType}\n`);
    testsPassed++;

    // Test 7: Typing Indicator
    console.log('📤 Test 7: Testing typing indicator...');
    socket.emit('typing:start', {
        groupId: 999,
        userId: 999,
        userName: 'Test User'
    });

    setTimeout(() => {
        socket.emit('typing:stop', {
            groupId: 999,
            userId: 999,
            userName: 'Test User'
        });
        testsPassed++;
        console.log('✅ Test 7: Typing events sent successfully\n');

        // Finish tests
        setTimeout(() => {
            printSummary();
            socket.disconnect();
            process.exit(0);
        }, 1000);
    }, 1000);
});

socket.on('typing:user', (data) => {
    console.log(`   Typing event received: ${data.userName} ${data.isTyping ? 'is typing' : 'stopped typing'}`);
});

socket.on('connect_error', (error) => {
    console.log('❌ Connection Error:', error.message);
    console.log('\n⚠️  Make sure the Socket.IO server is running:');
    console.log('   npm run socket\n');
    testsFailed++;
    process.exit(1);
});

socket.on('disconnect', () => {
    console.log('\n🔌 Disconnected from server');
});

function printSummary() {
    console.log('\n' + '='.repeat(50));
    console.log('📊 Test Summary');
    console.log('='.repeat(50));
    console.log(`✅ Tests Passed: ${testsPassed}`);
    console.log(`❌ Tests Failed: ${testsFailed}`);
    console.log(`📈 Total Tests: ${testsPassed + testsFailed}`);
    
    if (testsPassed >= 6) {
        console.log('\n🎉 All tests passed! Socket.IO server is working correctly!');
    } else {
        console.log('\n⚠️  Some tests failed. Check the server logs.');
    }
    console.log('='.repeat(50) + '\n');
}

// Timeout after 10 seconds
setTimeout(() => {
    console.log('\n⏱️  Test timeout after 10 seconds');
    printSummary();
    socket.disconnect();
    process.exit(1);
}, 10000);
