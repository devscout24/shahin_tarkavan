import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// এই টোকেনটি অবশ্যই আপনার অ্যাপের স্টোরেজ থেকে নিতে হবে
const token = localStorage.getItem('token'); 

window.Echo = new Echo({
    broadcaster: 'reverb', // রিভার্ব মোড ব্যবহার করুন
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    
    // নিচে এই ২টি অংশ অবশ্যই যোগ করুন
    authEndpoint: '/api/broadcasting/auth', // এপিআই প্রিফিক্স নিশ্চিত করুন
    auth: {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    },
});