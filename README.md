# Laravel Messaging Attachments

Attach files and media to messages in [phunky/laravel-messaging](https://github.com/phunky/laravel-messaging) conversations. This extension stores attachment metadata (path, disk, MIME type, size, and arbitrary JSON) alongside each message, while leaving actual file storage entirely to your application.

## Requirements

- PHP ^8.4
- `[phunky/laravel-messaging](https://packagist.org/packages/phunky/laravel-messaging)` ^0.0.1

## Installation

```bash
composer require phunky/laravel-messaging-attachments
```

Register the extension in `config/messaging.php`:

```php
'extensions' => [
    \Phunky\LaravelMessagingAttachments\AttachmentExtension::class,
],
```

Run migrations:

```bash
php artisan migrate
```

## Usage

Inject `AttachmentService` — or resolve it from the container — wherever you handle uploads.

```php
use Phunky\LaravelMessagingAttachments\AttachmentService;

class MessageController extends Controller
{
    public function __construct(private AttachmentService $attachments) {}

    public function store(Request $request, Message $message)
    {
        $path = $request->file('file')->store('attachments', 'public');

        $attachment = $this->attachments->attach($message, $request->user(), [
            'type'      => 'image',
            'path'      => $path,
            'filename'  => $request->file('file')->getClientOriginalName(),
            'disk'      => 'public',
            'mime_type' => $request->file('file')->getMimeType(),
            'size'      => $request->file('file')->getSize(),
        ]);
    }
}
```

### Attaching files

```php
// Single attachment — returns Attachment model
$attachment = $attachmentService->attach($message, $sender, [
    'type'      => 'image',          // required — e.g. 'image', 'video', 'document', 'audio'
    'path'      => 'uploads/a.jpg',  // required — path on the disk
    'filename'  => 'photo.jpg',      // required — original file name
    'disk'      => 'public',         // optional — Laravel filesystem disk
    'url'       => 'https://...',    // optional — direct or CDN URL
    'mime_type' => 'image/jpeg',     // optional
    'size'      => 204800,           // optional — size in bytes
    'order'     => 0,                // optional — position; auto-increments when omitted
    'meta'      => ['width' => 800], // optional — arbitrary JSON data
]);

// Multiple attachments in one transaction — returns Collection<Attachment>
$attachments = $attachmentService->attachMany($message, $sender, [
    ['type' => 'image', 'path' => 'a.jpg', 'filename' => 'a.jpg'],
    ['type' => 'image', 'path' => 'b.jpg', 'filename' => 'b.jpg'],
]);
```

Only the original message sender can attach or detach files. Attempts by anyone else throw `Phunky\LaravelMessaging\Exceptions\CannotMessageException`.

### Removing an attachment

```php
// Deletes the record and dispatches AttachmentDetached
$attachmentService->detach($message, $sender, $attachment);
```

### Fetching attachments

```php
// All attachments for a message, ordered by position then id
$attachments = $attachmentService->getAttachments($message);

// All attachments across a whole conversation,
// ordered by message sent_at (newest first), then attachment order and id
$attachments = $attachmentService->getAttachmentsForConversation($conversation);
```

### Relationship macro

`Message::attachments()` is registered as a `hasMany` macro — call it as a method, not a property:

```php
$message->refresh();
$message->attachments()->get();   // returns all Attachment models
$message->attachments()->count();
```

## Events

Both events use `Dispatchable` and `SerializesModels` and are safe to queue.


| Event                | Properties                                                               |
| -------------------- | ------------------------------------------------------------------------ |
| `AttachmentAttached` | `Attachment $attachment`, `Message $message`, `Messageable $messageable` |
| `AttachmentDetached` | `Message $message`, `Messageable $messageable`, `int|string $attachmentId` |


```php
use Phunky\LaravelMessagingAttachments\Events\AttachmentAttached;

Event::listen(AttachmentAttached::class, function (AttachmentAttached $event) {
    // Generate a thumbnail, push a notification, etc.
    GenerateThumbnail::dispatch($event->attachment->path, $event->attachment->disk);
});
```

`AttachmentDetached` carries the `$attachmentId` rather than a model because the record has already been deleted by the time the event fires.

## Storage

This extension is **storage-agnostic** — it records where a file lives, not the file itself. Upload the file through Laravel's filesystem first, then pass the resulting path and disk to `attach()`. This works with any driver: `local`, `public`, `s3`, or any custom disk.

```php
// Local disk
$path = $request->file('avatar')->store('avatars', 'local');

// S3
$path = $request->file('document')->store('documents', 's3');

// Then record the attachment
$attachmentService->attach($message, $user, [
    'type'     => 'document',
    'path'     => $path,
    'filename' => $request->file('document')->getClientOriginalName(),
    'disk'     => 's3',
]);
```

Deleting the underlying file from storage when an attachment is detached is your application's responsibility — listen to `AttachmentDetached` and remove the file using `Storage::disk($disk)->delete($path)`.

## License

MIT - see [LICENSE.md](LICENSE.md).