<?php

// Broadcast channels were removed with Reverb/WebSockets — live updates now
// flow through Electric SQL shapes (read-path) instead of broadcast events.
// Re-add Broadcast::channel(...) definitions here only if server push is
// reintroduced.
