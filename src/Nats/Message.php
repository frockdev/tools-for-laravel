<?php
namespace FrockDev\ToolsForLaravel\Nats;

/**
 * Message Class.
 *
 * @package Nats
 */
class Message
{
    /**
     * Message Subject.
     *
     * @var string
     */
    private $subject;

    /**
     * Subject reply to
     *
     * @var string|null
     */
    private $replyTo = null;

    /**
     * Message Body.
     *
     * @var mixed
     */
    public mixed $body;

    /**
     * Message Ssid.
     *
     * @var string
     */
    private $sid;

    /**
     * Message related connection.
     *
     * @var Connection
     */
    private $conn;


    /**
     * Message constructor.
     *
     * @param string     $subject Message subject.
     * @param mixed     $body    Message body.
     * @param string     $sid     Message Sid.
     * @param Connection $conn    Message Connection.
     */
    public function __construct(string $subject, $body, string $sid, Connection $conn)
    {
        $this->setSubject($subject);
        $this->setBody($body);
        $this->setSid($sid);
        $this->setConn($conn);
    }


    /**
     * Set subject.
     *
     * @param string $subject Subject.
     *
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get subject.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set reply to subject
     *
     * @param string $subject
     * @return $this
     */
    public function setReplyTo($subject)
    {
        if (!empty($subject) && is_string($subject)) {
            $this->replyTo = $subject;
        }

        return $this;
    }

    /**
     * Get reply to subject
     *
     * @return string|null
     */
    public function getReplyTo()
    {
        return $this->replyTo;
    }

    /**
     * Set body.
     *
     * @param string $body Body.
     *
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }


    /**
     * Get body.
     *
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }


    /**
     * Set Ssid.
     *
     * @param string $sid Ssid.
     *
     * @return $this
     */
    public function setSid($sid)
    {
        $this->sid = $sid;
        return $this;
    }


    /**
     * Get Ssid.
     *
     * @return string
     */
    public function getSid()
    {
        return $this->sid;
    }


    /**
     * String representation of a message.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getBody();
    }


    /**
     * Set Conn.
     *
     * @param Connection $conn Connection.
     *
     * @return $this
     */
    public function setConn(Connection $conn)
    {
        $this->conn = $conn;
        return $this;
    }


    /**
     * Get Conn.
     *
     * @return Connection
     */
    public function getConn()
    {
        return $this->conn;
    }


    /**
     * Allows you reply the message with a specific body.
     *
     * @param string $body Body to be set.
     *
     * @return void
     */
    public function reply($body)
    {
        if (empty($this->replyTo)) {
            throw new \Exception('Can\'t reply to empty subject');
        }

        $this->conn->publish(
            $this->replyTo,
            $body
        );
    }
}
