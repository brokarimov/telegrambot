import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'cec5-185-213-230-43.ngrok-free.app',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss', 'polling'],
});





window.Echo.channel('orderStatus')
    .listen('OrderStatusEvent', (e) => {
        console.log(e);

        const statusMapping = {
            0: 'pendingOrders',
            1: 'acceptedOrders',
            2: 'rejectedOrders',
        };

        const order = e.data;
        const statusKey = statusMapping[order.status]; 
        const targetList = document.getElementById(statusKey);

        if (targetList) {
            
            const existingOrder = targetList.querySelector(`li[data-id="${order.id}"]`);
            if (existingOrder) {
                existingOrder.remove(); 
            }

            const newOrder = document.createElement('li');
            newOrder.setAttribute('data-id', order.id);
            newOrder.innerHTML = `Order N${order.id}<br>`;

            order.orderItems.forEach((item) => {
                newOrder.innerHTML += `- ${item.foods.name}<br>`;
            });

            targetList.prepend(newOrder);
        }
    });



