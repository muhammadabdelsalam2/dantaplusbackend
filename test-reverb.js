import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: "pusher",
    key: "YOUR_KEY",
    cluster: "eu",
});

echo.channel("chat.1")
    .listen("message.sent", (data) => {
        console.log(data);
    });