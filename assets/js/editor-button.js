/* global pldEditorData, wp */
const el = wp.element.createElement;
const registerPlugin = wp.plugins.registerPlugin;
const PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
const { Button, TextControl, VisuallyHidden, CheckboxControl } = wp.components;
const { useCopyToClipboard } = wp.compose;
const { useDispatch } = wp.data;
const { __ } = wp.i18n;
const noticesStore = wp.notices.store;

function PLDCopyButton({ text }) {
  const { createNotice } = useDispatch(noticesStore);
  const ref = useCopyToClipboard(text, () => {
    createNotice("info", pldEditorData.copiedNotice, {
      isDismissible: true,
      type: "snackbar",
    });
  });
  return el(Button, {
    ref,
    label: pldEditorData.copyLabel,
    className: "pld-copy-btn",
    size: "small",
    icon: "admin-page",
  });
}

function PLDPublicPreviewStatusInfo() {
  if (!pldEditorData || !pldEditorData.postId) {
    return null;
  }
  const { createNotice } = useDispatch(noticesStore);
  const [enabled, setEnabled] = wp.element.useState(
    !!pldEditorData.previewEnabled
  );
  const [link, setLink] = wp.element.useState(
    enabled ? pldEditorData.link : ""
  );

  const toggle = (checked) => {
    const data = new window.FormData();
    data.append("action", "pld_toggle_public_preview");
    data.append("_ajax_nonce", pldEditorData.nonce);
    data.append("post_ID", String(pldEditorData.postId));
    data.append("checked", checked ? "true" : "false");

    window
      .fetch(pldEditorData.ajaxUrl, { method: "POST", body: data })
      .then((res) => res.json())
      .then((res) => {
        if (!res.success) throw res;
        const newEnabled = !!checked;
        setEnabled(newEnabled);
        setLink(
          newEnabled && res?.data?.preview_url ? res.data.preview_url : ""
        );
        createNotice(
          "info",
          newEnabled
            ? __("Public preview enabled.", "public-links-drafts")
            : __("Public preview disabled.", "public-links-drafts"),
          { isDismissible: true, type: "snackbar" }
        );
      })
      .catch(() => {
        createNotice(
          "error",
          __(
            "Error while changing the public preview status.",
            "public-links-drafts"
          ),
          { isDismissible: true, type: "snackbar" }
        );
      });
  };

  return el(
    PluginPostStatusInfo,
    { className: "pld-public-preview-status-info" },
    el(CheckboxControl, {
      label: __("Enable public preview", "public-links-drafts"),
      checked: enabled,
      onChange: (checked) => toggle(checked),
    }),
    enabled &&
      el(
        Button,
        { variant: "secondary", isLink: true, href: link, target: "_blank" },
        pldEditorData.text
      ),
    enabled &&
      el(
        "div",
        { className: "pld-preview-url" },
        el(VisuallyHidden, { as: "label" }, "Preview URL"),
        el(TextControl, { value: link, readOnly: true }),
        el(PLDCopyButton, { text: link })
      )
  );
}

registerPlugin("pld-public-preview-status", {
  render: PLDPublicPreviewStatusInfo,
});
