<?php
/**
 * Prompt Sections Partial - 7 Structured Components
 * 
 * @package DirectReach_Campaign_Builder
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Prompt Sections -->
<div class="form-section form-section-prompts">
    <div class="section-header">
        <h3>
            <i class="fas fa-robot"></i>
            AI Prompt Structure
        </h3>
        <p class="section-description">
            Define how AI should generate emails. Each section guides the AI's behavior.
        </p>
    </div>

    <!-- 1. Persona -->
    <div class="form-group">
        <label for="prompt_persona">
            <span class="label-number">1.</span>
            Persona
            <span class="badge-optional">Optional</span>
        </label>
        <textarea 
            id="prompt_persona" 
            name="prompt_persona"
            class="form-control"
            rows="4"
            placeholder="Define who is writing the email and their role...

Example:
You are a helpful sales consultant at a B2B marketing software company. You're knowledgeable but not pushy, and you genuinely want to help prospects solve their problems."
        ></textarea>
        <small class="help-text">
            Who is writing? What's their perspective and authority?
        </small>
    </div>

    <!-- 2. Style -->
    <div class="form-group">
        <label for="prompt_style">
            <span class="label-number">2.</span>
            Style Rules
            <span class="badge-optional">Optional</span>
        </label>
        <textarea 
            id="prompt_style" 
            name="prompt_style"
            class="form-control"
            rows="4"
            placeholder="Define tone, voice, and writing style...

Example:
- Conversational and warm, like a colleague
- Professional but approachable
- Use short sentences and paragraphs
- Avoid jargon unless industry-specific"
        ></textarea>
        <small class="help-text">
            How should the email sound? Formal vs casual, etc.
        </small>
    </div>

    <!-- 3. Output -->
    <div class="form-group">
        <label for="prompt_output">
            <span class="label-number">3.</span>
            Output Specification
            <span class="badge-optional">Optional</span>
        </label>
        <textarea 
            id="prompt_output" 
            name="prompt_output"
            class="form-control"
            rows="4"
            placeholder="Specify the structure and format...

Example:
Generate a subject line and email body.
- Subject: 6-8 words, question or curiosity-driven
- Body: 3-4 short paragraphs, 150-200 words total
- Include one content link naturally"
        ></textarea>
        <small class="help-text">
            What should the output look like? Structure, length, format.
        </small>
    </div>

    <!-- 4. Personalization -->
    <div class="form-group">
        <label for="prompt_personalization">
            <span class="label-number">4.</span>
            Personalization Guidelines
            <span class="badge-optional">Optional</span>
        </label>
        <textarea 
            id="prompt_personalization" 
            name="prompt_personalization"
            class="form-control"
            rows="4"
            placeholder="How to use prospect data...

Example:
- Reference recent page visits naturally
- Mention company name once
- Acknowledge their role if known
- Connect their industry to relevant challenges"
        ></textarea>
        <small class="help-text">
            How should AI incorporate prospect-specific information?
        </small>
    </div>

    <!-- 5. Constraints -->
    <div class="form-group">
        <label for="prompt_constraints">
            <span class="label-number">5.</span>
            Constraints
            <span class="badge-optional">Optional</span>
        </label>
        <textarea 
            id="prompt_constraints" 
            name="prompt_constraints"
            class="form-control"
            rows="4"
            placeholder="What to avoid or limitations...

Example:
- Never make promises about results
- Don't mention pricing or discounts
- Avoid sounding desperate or pushy
- Keep under 200 words total"
        ></textarea>
        <small class="help-text">
            What should AI NOT do? Hard rules and boundaries.
        </small>
    </div>

    <!-- 6. Examples -->
    <div class="form-group">
        <label for="prompt_examples">
            <span class="label-number">6.</span>
            Examples (Few-Shot Learning)
            <span class="badge-optional">Optional</span>
        </label>
        <textarea 
            id="prompt_examples" 
            name="prompt_examples"
            class="form-control"
            rows="6"
            placeholder="Provide 1-2 example emails to guide AI...

Example:
---
GOOD EXAMPLE:
Subject: Quick question about your pipeline

Hi Sarah,

I noticed someone from Acme Corp was checking out our ROI calculator...
---"
        ></textarea>
        <small class="help-text">
            Show AI what good looks like.
        </small>
    </div>

    <!-- 7. Context -->
    <div class="form-group">
        <label for="prompt_context">
            <span class="label-number">7.</span>
            Context Instructions
            <span class="badge-optional">Optional</span>
        </label>
        <textarea 
            id="prompt_context" 
            name="prompt_context"
            class="form-control"
            rows="4"
            placeholder="How to interpret the context data...

Example:
You'll receive prospect data, recent page visits, and available content links.
Select the most relevant content based on their behavior."
        ></textarea>
        <small class="help-text">
            Help AI understand the additional context it will receive.
        </small>
    </div>
</div>