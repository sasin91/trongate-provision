
    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <!-- What will happen -->
    <div class="what-happens">
        <div class="what-happens-item">
            <span class="check">&#10003;</span>
            <span>Your token is <strong>validated</strong> against the Hetzner API</span>
        </div>
        <div class="what-happens-item">
            <span class="check">&#10003;</span>
            <span>Your SSH public key is <strong>uploaded</strong> to Hetzner — new servers will accept it at first boot</span>
        </div>
        <div class="what-happens-item">
            <span class="check">&#10003;</span>
            <span>Your token is <strong>encrypted</strong> and stored - never logged in full</span>
        </div>
        <div class="what-happens-item">
            <span class="check">&#10148;</span>
            <span>Next you'll choose or create the <strong>Hetzner server</strong> to provision with IPv4 and IPv6 enabled</span>
        </div>
    </div>

    <!-- How to get token -->
    <div class="guidance">
        <h4>How to get your API token</h4>
        <ol>
            <li>Log in to <a href="https://console.hetzner.cloud" target="_blank" rel="noopener">console.hetzner.cloud</a></li>
            <li>Open your project (or create one)</li>
            <li>Go to <strong>Security</strong> &rarr; <strong>API Tokens</strong></li>
            <li>Click <strong>Generate API Token</strong></li>
            <li>Select <code>Read &amp; Write</code> and copy the token</li>
        </ol>
    </div>

    <?= form_open('customer-onboarding/server_hetzner') ?>

    <div class="form-group">
        <label class="form-label" for="token">Hetzner API Token</label>
        <input type="password" name="token" id="token" class="form-input"
            placeholder="Paste your Read &amp; Write token here"
            autocomplete="off"
            value="<?= htmlspecialchars($existing_token) ?>"
            required autofocus>
    </div>

    <?= wizard_step_dots(wizard_step_classes(8, 4)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Connect Hetzner &amp; Continue &#10148;
    </button>
    <?= form_close() ?>
