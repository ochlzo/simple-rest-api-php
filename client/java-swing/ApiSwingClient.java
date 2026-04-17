import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Dimension;
import java.awt.FlowLayout;
import java.awt.Font;
import java.awt.GridBagConstraints;
import java.awt.GridBagLayout;
import java.awt.Insets;
import java.io.IOException;
import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.LinkedHashMap;
import java.util.Map;
import javax.swing.BorderFactory;
import javax.swing.Box;
import javax.swing.BoxLayout;
import javax.swing.JButton;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JOptionPane;
import javax.swing.JPanel;
import javax.swing.JPasswordField;
import javax.swing.JScrollPane;
import javax.swing.JSplitPane;
import javax.swing.JTabbedPane;
import javax.swing.JTextArea;
import javax.swing.JTextField;
import javax.swing.SwingUtilities;
import javax.swing.UIManager;

public class ApiSwingClient extends JFrame {
    private static final String DEFAULT_BASE_URL = "http://127.0.0.1:8000";
    private static final DateTimeFormatter TIMESTAMP_FORMAT =
        DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss");

    private final HttpClient httpClient = HttpClient.newHttpClient();
    private final JTextField baseUrlField = new JTextField(DEFAULT_BASE_URL, 28);
    private final JTextArea responseArea = new JTextArea();
    private final JLabel statusLabel = new JLabel("Ready. Start your PHP server to reach the Supabase-backed API.");

    public ApiSwingClient() {
        super("Supabase REST API Swing Client");
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setMinimumSize(new Dimension(960, 640));
        setLocationRelativeTo(null);
        setLayout(new BorderLayout(12, 12));

        JPanel headerPanel = buildHeaderPanel();
        JSplitPane contentPane = new JSplitPane(JSplitPane.HORIZONTAL_SPLIT, buildTabs(), buildResponsePanel());
        contentPane.setResizeWeight(0.54);
        contentPane.setBorder(BorderFactory.createEmptyBorder());

        add(headerPanel, BorderLayout.NORTH);
        add(contentPane, BorderLayout.CENTER);
        add(buildFooterPanel(), BorderLayout.SOUTH);
    }

    private JPanel buildHeaderPanel() {
        JPanel panel = new JPanel(new BorderLayout(12, 12));
        panel.setBorder(BorderFactory.createEmptyBorder(12, 12, 0, 12));

        JLabel title = new JLabel("Supabase REST API Desktop Client");
        title.setFont(new Font("Segoe UI", Font.BOLD, 20));

        JTextArea description = new JTextArea(
            "This one-file Swing client uses the same requests as the Python client and sends them to your PHP API. "
                + "The PHP server then reads and writes data from the Supabase PostgreSQL database."
        );
        description.setEditable(false);
        description.setOpaque(false);
        description.setLineWrap(true);
        description.setWrapStyleWord(true);
        description.setFont(new Font("Segoe UI", Font.PLAIN, 13));

        JPanel baseUrlPanel = new JPanel(new FlowLayout(FlowLayout.LEFT, 8, 0));
        baseUrlPanel.setBorder(BorderFactory.createTitledBorder("Connection"));

        JButton rootButton = new JButton("Health Check");
        rootButton.addActionListener(event -> sendRequest("GET", "/", null, null));

        baseUrlPanel.add(new JLabel("Base URL"));
        baseUrlPanel.add(baseUrlField);
        baseUrlPanel.add(rootButton);

        JPanel textPanel = new JPanel();
        textPanel.setLayout(new BoxLayout(textPanel, BoxLayout.Y_AXIS));
        textPanel.add(title);
        textPanel.add(Box.createVerticalStrut(8));
        textPanel.add(description);

        panel.add(textPanel, BorderLayout.CENTER);
        panel.add(baseUrlPanel, BorderLayout.SOUTH);
        return panel;
    }

    private JTabbedPane buildTabs() {
        JTabbedPane tabs = new JTabbedPane();
        tabs.addTab("Signup", buildSignupPanel());
        tabs.addTab("Login", buildLoginPanel());
        tabs.addTab("Users", buildUsersPanel());
        tabs.addTab("Update", buildUpdatePanel());
        tabs.addTab("Delete", buildDeletePanel());
        return tabs;
    }

    private JPanel buildSignupPanel() {
        JTextField nameField = new JTextField();
        JTextField emailField = new JTextField();
        JPasswordField passwordField = new JPasswordField();

        JButton submitButton = new JButton("Create User");
        submitButton.addActionListener(event -> {
            LinkedHashMap<String, String> body = new LinkedHashMap<>();
            body.put("name", nameField.getText().trim());
            body.put("email", emailField.getText().trim());
            body.put("password", readPassword(passwordField));
            sendRequest("POST", "/signup", null, body);
        });

        return formPanel(
            "Create a user in the Supabase-backed user_demo table.",
            new JLabel("Name"), nameField,
            new JLabel("Email"), emailField,
            new JLabel("Password"), passwordField,
            submitButton
        );
    }

    private JPanel buildLoginPanel() {
        JTextField emailField = new JTextField();
        JPasswordField passwordField = new JPasswordField();

        JButton submitButton = new JButton("Validate Login");
        submitButton.addActionListener(event -> {
            LinkedHashMap<String, String> body = new LinkedHashMap<>();
            body.put("email", emailField.getText().trim());
            body.put("password", readPassword(passwordField));
            sendRequest("POST", "/login", null, body);
        });

        return formPanel(
            "Check a user's credentials through the /login endpoint.",
            new JLabel("Email"), emailField,
            new JLabel("Password"), passwordField,
            submitButton
        );
    }

    private JPanel buildUsersPanel() {
        JTextField emailField = new JTextField();

        JButton getAllButton = new JButton("Get All Users");
        getAllButton.addActionListener(event -> sendRequest("GET", "/users", null, null));

        JButton searchButton = new JButton("Find By Email");
        searchButton.addActionListener(event -> {
            LinkedHashMap<String, String> query = new LinkedHashMap<>();
            query.put("email", emailField.getText().trim());
            sendRequest("GET", "/users", query, null);
        });

        JPanel actions = new JPanel(new FlowLayout(FlowLayout.LEFT, 8, 0));
        actions.add(getAllButton);
        actions.add(searchButton);

        return formPanel(
            "Read all users or fetch a single record from Supabase by email.",
            new JLabel("Email"), emailField,
            actions
        );
    }

    private JPanel buildUpdatePanel() {
        JTextField emailField = new JTextField();
        JTextField newEmailField = new JTextField();
        JTextField newNameField = new JTextField();
        JPasswordField newPasswordField = new JPasswordField();

        JButton submitButton = new JButton("Update User");
        submitButton.addActionListener(event -> {
            LinkedHashMap<String, String> body = new LinkedHashMap<>();
            body.put("email", emailField.getText().trim());
            body.put("new-email", newEmailField.getText().trim());
            body.put("new-name", newNameField.getText().trim());
            body.put("new-password", readPassword(newPasswordField));
            sendRequest("PUT", "/update", null, body);
        });

        return formPanel(
            "Update a user with the same field names used by the PHP API and Python client.",
            new JLabel("Current Email"), emailField,
            new JLabel("New Email"), newEmailField,
            new JLabel("New Name"), newNameField,
            new JLabel("New Password"), newPasswordField,
            submitButton
        );
    }

    private JPanel buildDeletePanel() {
        JTextField emailField = new JTextField();

        JButton submitButton = new JButton("Delete User");
        submitButton.addActionListener(event -> {
            LinkedHashMap<String, String> query = new LinkedHashMap<>();
            query.put("email", emailField.getText().trim());
            sendRequest("DELETE", "/delete", query, null);
        });

        return formPanel(
            "Delete a user record by email through the API.",
            new JLabel("Email"), emailField,
            submitButton
        );
    }

    private JPanel formPanel(String description, Object... items) {
        JPanel panel = new JPanel(new BorderLayout(0, 12));
        panel.setBorder(BorderFactory.createEmptyBorder(12, 12, 12, 12));

        JTextArea intro = new JTextArea(description);
        intro.setEditable(false);
        intro.setOpaque(false);
        intro.setLineWrap(true);
        intro.setWrapStyleWord(true);
        intro.setFont(new Font("Segoe UI", Font.PLAIN, 13));

        JPanel fields = new JPanel(new GridBagLayout());
        GridBagConstraints gbc = new GridBagConstraints();
        gbc.insets = new Insets(6, 6, 6, 6);
        gbc.fill = GridBagConstraints.HORIZONTAL;
        gbc.anchor = GridBagConstraints.NORTHWEST;

        int row = 0;
        for (int index = 0; index < items.length; index++) {
            Object item = items[index];

            if (item instanceof JLabel label && index + 1 < items.length && items[index + 1] instanceof JTextField field) {
                gbc.gridx = 0;
                gbc.gridy = row;
                gbc.weightx = 0;
                fields.add(label, gbc);

                gbc.gridx = 1;
                gbc.weightx = 1;
                fields.add(field, gbc);
                row++;
                index++;
                continue;
            }

            if (item instanceof JPanel actionPanel) {
                gbc.gridx = 0;
                gbc.gridy = row;
                gbc.gridwidth = 2;
                gbc.weightx = 1;
                fields.add(actionPanel, gbc);
                gbc.gridwidth = 1;
                row++;
                continue;
            }

            if (item instanceof JButton button) {
                JPanel actionPanel = new JPanel(new FlowLayout(FlowLayout.LEFT, 0, 0));
                actionPanel.add(button);
                gbc.gridx = 0;
                gbc.gridy = row;
                gbc.gridwidth = 2;
                gbc.weightx = 1;
                fields.add(actionPanel, gbc);
                gbc.gridwidth = 1;
                row++;
            }
        }

        gbc.gridx = 0;
        gbc.gridy = row;
        gbc.weighty = 1;
        gbc.fill = GridBagConstraints.BOTH;
        fields.add(new JPanel(), gbc);

        panel.add(intro, BorderLayout.NORTH);
        panel.add(fields, BorderLayout.CENTER);
        return panel;
    }

    private JScrollPane buildResponsePanel() {
        responseArea.setEditable(false);
        responseArea.setFont(new Font(Font.MONOSPACED, Font.PLAIN, 13));
        responseArea.setLineWrap(true);
        responseArea.setWrapStyleWord(true);
        responseArea.setBorder(BorderFactory.createEmptyBorder(8, 8, 8, 8));
        responseArea.setText("Responses will appear here.\n\nStart the PHP server first:\nphp -S 127.0.0.1:8000 server.php");

        JScrollPane scrollPane = new JScrollPane(responseArea);
        scrollPane.setBorder(BorderFactory.createTitledBorder("API Response"));
        return scrollPane;
    }

    private JPanel buildFooterPanel() {
        JPanel panel = new JPanel(new BorderLayout());
        panel.setBorder(BorderFactory.createEmptyBorder(0, 12, 12, 12));
        statusLabel.setForeground(new Color(0x1F4B99));
        panel.add(statusLabel, BorderLayout.CENTER);
        return panel;
    }

    private void sendRequest(String method, String path, Map<String, String> query, Map<String, String> body) {
        String baseUrl = baseUrlField.getText().trim();
        if (baseUrl.isEmpty()) {
            JOptionPane.showMessageDialog(this, "Enter a base URL first.", "Missing Base URL", JOptionPane.WARNING_MESSAGE);
            return;
        }

        statusLabel.setText("Sending " + method + " " + path + " ...");

        Thread worker = new Thread(() -> {
            try {
                String targetUrl = buildUrl(baseUrl, path, query);
                HttpRequest.Builder requestBuilder = HttpRequest.newBuilder()
                    .uri(URI.create(targetUrl))
                    .header("Accept", "application/json");

                if (body == null) {
                    requestBuilder.method(method, HttpRequest.BodyPublishers.noBody());
                } else {
                    requestBuilder.header("Content-Type", "application/json");
                    requestBuilder.method(method, HttpRequest.BodyPublishers.ofString(buildJson(body)));
                }

                HttpResponse<String> response = httpClient.send(
                    requestBuilder.build(),
                    HttpResponse.BodyHandlers.ofString()
                );

                SwingUtilities.invokeLater(() ->
                    displayResult(response.statusCode(), response.body(), null, targetUrl, method)
                );
            } catch (IOException | InterruptedException ex) {
                if (ex instanceof InterruptedException) {
                    Thread.currentThread().interrupt();
                }
                SwingUtilities.invokeLater(() ->
                    displayResult(-1, "", ex.getMessage(), path, method)
                );
            } catch (IllegalArgumentException ex) {
                SwingUtilities.invokeLater(() ->
                    displayResult(-1, "", "Invalid URL. Check the base URL.", path, method)
                );
            }
        });
        worker.start();
    }

    private void displayResult(int statusCode, String body, String errorMessage, String requestTarget, String method) {
        String timestamp = LocalDateTime.now().format(TIMESTAMP_FORMAT);
        StringBuilder output = new StringBuilder();
        output.append("Time: ").append(timestamp).append('\n')
            .append("Request: ").append(method).append(' ').append(requestTarget).append('\n');

        if (errorMessage != null) {
            output.append("Error: ").append(errorMessage).append('\n');
            statusLabel.setText("Request failed before reaching the API.");
        } else {
            output.append("HTTP Status: ").append(statusCode).append('\n').append('\n');
            output.append(formatJson(body));
            statusLabel.setText("Response received with HTTP " + statusCode + ".");
        }

        responseArea.setText(output.toString());
        responseArea.setCaretPosition(0);
    }

    private String buildUrl(String baseUrl, String path, Map<String, String> query) {
        String normalizedBase = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length() - 1) : baseUrl;
        StringBuilder builder = new StringBuilder(normalizedBase).append(path);

        if (query != null && !query.isEmpty()) {
            boolean first = true;
            for (Map.Entry<String, String> entry : query.entrySet()) {
                String value = entry.getValue();
                if (value == null || value.isBlank()) {
                    continue;
                }

                builder.append(first ? '?' : '&');
                builder.append(URLEncoder.encode(entry.getKey(), StandardCharsets.UTF_8));
                builder.append('=');
                builder.append(URLEncoder.encode(value, StandardCharsets.UTF_8));
                first = false;
            }
        }

        return builder.toString();
    }

    private String buildJson(Map<String, String> body) {
        StringBuilder builder = new StringBuilder();
        builder.append('{');

        boolean first = true;
        for (Map.Entry<String, String> entry : body.entrySet()) {
            if (!first) {
                builder.append(',');
            }
            builder
                .append(jsonString(entry.getKey()))
                .append(':')
                .append(jsonString(entry.getValue() == null ? "" : entry.getValue()));
            first = false;
        }

        builder.append('}');
        return builder.toString();
    }

    private String jsonString(String value) {
        String escaped = value
            .replace("\\", "\\\\")
            .replace("\"", "\\\"")
            .replace("\n", "\\n")
            .replace("\r", "\\r")
            .replace("\t", "\\t");

        return "\"" + escaped + "\"";
    }

    private String formatJson(String body) {
        if (body == null || body.isBlank()) {
            return "No response body returned.";
        }

        String trimmed = body.trim();
        if ((!trimmed.startsWith("{") || !trimmed.endsWith("}"))
            && (!trimmed.startsWith("[") || !trimmed.endsWith("]"))) {
            return trimmed;
        }

        StringBuilder pretty = new StringBuilder();
        int indent = 0;
        boolean inString = false;
        boolean escaping = false;

        for (char ch : trimmed.toCharArray()) {
            if (escaping) {
                pretty.append(ch);
                escaping = false;
                continue;
            }

            if (ch == '\\' && inString) {
                pretty.append(ch);
                escaping = true;
                continue;
            }

            if (ch == '"') {
                inString = !inString;
                pretty.append(ch);
                continue;
            }

            if (inString) {
                pretty.append(ch);
                continue;
            }

            switch (ch) {
                case '{':
                case '[':
                    pretty.append(ch).append('\n');
                    indent++;
                    appendIndent(pretty, indent);
                    break;
                case '}':
                case ']':
                    pretty.append('\n');
                    indent = Math.max(0, indent - 1);
                    appendIndent(pretty, indent);
                    pretty.append(ch);
                    break;
                case ',':
                    pretty.append(ch).append('\n');
                    appendIndent(pretty, indent);
                    break;
                case ':':
                    pretty.append(": ");
                    break;
                default:
                    if (!Character.isWhitespace(ch)) {
                        pretty.append(ch);
                    }
                    break;
            }
        }

        return pretty.toString();
    }

    private void appendIndent(StringBuilder builder, int indent) {
        for (int count = 0; count < indent; count++) {
            builder.append("  ");
        }
    }

    private String readPassword(JPasswordField field) {
        return new String(field.getPassword()).trim();
    }

    public static void main(String[] args) {
        try {
            UIManager.setLookAndFeel(UIManager.getSystemLookAndFeelClassName());
        } catch (Exception ignored) {
        }

        SwingUtilities.invokeLater(() -> {
            ApiSwingClient client = new ApiSwingClient();
            client.setVisible(true);
        });
    }
}
